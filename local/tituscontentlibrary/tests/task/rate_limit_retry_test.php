<?php
namespace local_tituscontentlibrary\task;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/tituscontentlibrary/tests/fixtures/mock_titus_api_client.php');

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\exception\titus_rate_limit_exception;
use local_tituscontentlibrary\local\content_status;
use local_tituscontentlibrary\tests\fixtures\mock_titus_api_client;

/**
 * Tests that 429 rate-limit responses reschedule the task and keep the row PENDING.
 *
 * @covers \local_tituscontentlibrary\task\add_content_task
 */
class rate_limit_retry_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        client_factory::reset();
    }

    protected function tearDown(): void {
        client_factory::reset();
        parent::tearDown();
    }

    private function make_dto(string $id): content_dto {
        return new content_dto(
            id: $id,
            title: 'Rate Limit Test',
            description: '',
            short_description: '',
            thumbnail_url: '',
            category: '',
            tags: [],
            duration_minutes: 5,
            is_featured: false,
            is_new: false,
        );
    }

    private function insert_pending_row(string $contentid, int $userid): int {
        global $DB;
        $now = time();
        return $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => $contentid,
            'addedby'      => $userid,
            'status'       => content_status::PENDING,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_rate_limit_keeps_row_pending_and_reschedules(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_ratelimit';
        $dto       = $this->make_dto($contentid);
        $retryAfter = 120;

        $mock = (new mock_titus_api_client())
            ->with_catalogue([$dto])
            ->fail_on('get_signed_download_url', new titus_rate_limit_exception('Too many requests', 429, $retryAfter));

        client_factory::set_test_client($mock);

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = new add_content_task();
        $task->set_custom_data((object)[
            'contentid'  => $contentid,
            'userid'     => (int) $user->id,
            'addedrowid' => $rowid,
        ]);

        $before = time();
        ob_start();
        try {
            $task->execute();
            $this->fail('Expected titus_rate_limit_exception not thrown');
        } catch (titus_rate_limit_exception $e) {
            // Expected.
        } finally {
            ob_end_clean();
        }

        // Row must still be PENDING (not FAILED).
        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid], '*', MUST_EXIST);
        $this->assertSame(content_status::PENDING, $row->status);
        $this->assertEmpty($row->errormessage);

        // Task must be rescheduled.
        $nextRun = $task->get_next_run_time();
        $this->assertGreaterThan($before + $retryAfter - 2, $nextRun);
    }

    public function test_non_rate_limit_error_marks_row_failed(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_servererr';
        $dto       = $this->make_dto($contentid);

        $mock = (new mock_titus_api_client())
            ->with_catalogue([$dto])
            ->fail_on('get_signed_download_url', new \RuntimeException('Server crashed'));

        client_factory::set_test_client($mock);

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = new add_content_task();
        $task->set_custom_data((object)[
            'contentid'  => $contentid,
            'userid'     => (int) $user->id,
            'addedrowid' => $rowid,
        ]);

        ob_start();
        try {
            $task->execute();
            $this->fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            // Expected.
        } finally {
            ob_end_clean();
        }

        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid], '*', MUST_EXIST);
        $this->assertSame(content_status::FAILED, $row->status);
        $this->assertStringContainsString('Server crashed', $row->errormessage);
    }
}
