<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;
use local_tituscontentlibrary\local\content_status;

/**
 * Unit tests for the add_course external function.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\external\add_course
 */
class add_course_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        client_factory::set_test_client(null);
        // The plugin default add mode is now sync (decision D1). These tests assert
        // async queueing semantics, so pin async here; the sync-mode tests below
        // override this with set_config('addmode', 'sync', ...).
        set_config('addmode', 'async', 'local_tituscontentlibrary');
    }

    protected function tearDown(): void {
        client_factory::set_test_client(null);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return a user who has the addcontent capability at system context.
     */
    private function make_teacher(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:addcourse', CAP_ALLOW,
            $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        return $user;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * A fresh request should insert a row, queue an adhoc task, and return queued=true.
     */
    public function test_queues_task_on_valid_request(): void {
        global $DB;

        $user = $this->make_teacher();
        $this->setUser($user);

        $result = add_course::execute('content_abc123');

        $this->assertTrue($result['queued']);
        $this->assertFalse($result['alreadyqueued']);
        $this->assertSame(content_status::PENDING, $result['status']);
        $this->assertGreaterThan(0, $result['addedrowid']);

        // DB row should exist.
        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $result['addedrowid']]);
        $this->assertNotFalse($row);
        $this->assertSame('content_abc123', $row->contentid);
        $this->assertSame(content_status::PENDING, $row->status);

        // Adhoc task should be queued.
        $tasks = \core\task\manager::get_adhoc_tasks(\local_tituscontentlibrary\task\add_content_task::class);
        $this->assertCount(1, $tasks);
    }

    /**
     * Calling execute() twice with the same contentid should return alreadyqueued=true on the second call.
     */
    public function test_duplicate_returns_alreadyqueued(): void {
        $user = $this->make_teacher();
        $this->setUser($user);

        $first  = add_course::execute('content_dup');
        $second = add_course::execute('content_dup');

        $this->assertTrue($first['queued']);

        $this->assertFalse($second['queued']);
        $this->assertTrue($second['alreadyqueued']);
        $this->assertSame($first['addedrowid'], $second['addedrowid']);
    }

    /**
     * Re-adding a contentid whose row is in the 'failed' state should reset it to
     * pending, clear the error message, re-queue the task, and return queued=true.
     */
    public function test_failed_row_is_reset_and_requeued(): void {
        global $DB;

        $user = $this->make_teacher();
        $this->setUser($user);

        // Seed a previously-failed record.
        $now = time();
        $rowid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'content_failed',
            'addedby'      => $user->id,
            'status'       => content_status::FAILED,
            'errormessage' => 'Boom: download failed',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $result = add_course::execute('content_failed');

        // Should report a fresh queue, not "already queued".
        $this->assertTrue($result['queued']);
        $this->assertFalse($result['alreadyqueued']);
        $this->assertSame(content_status::PENDING, $result['status']);
        $this->assertSame($rowid, $result['addedrowid']);

        // The same row should be reused, now pending with the error cleared.
        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid]);
        $this->assertSame(content_status::PENDING, $row->status);
        $this->assertNull($row->errormessage);

        // A new adhoc task should have been queued for this row.
        $tasks = \core\task\manager::get_adhoc_tasks(\local_tituscontentlibrary\task\add_content_task::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertSame($rowid, (int)$task->get_custom_data()->addedrowid);
    }

    /**
     * Re-adding a contentid whose row is 'completed' should report alreadyqueued
     * and must NOT queue another task.
     */
    public function test_completed_row_returns_alreadyqueued(): void {
        global $DB;

        $user = $this->make_teacher();
        $this->setUser($user);

        $now = time();
        $rowid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'content_done',
            'addedby'      => $user->id,
            'status'       => content_status::COMPLETED,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $result = add_course::execute('content_done');

        $this->assertFalse($result['queued']);
        $this->assertTrue($result['alreadyqueued']);
        $this->assertSame(content_status::COMPLETED, $result['status']);
        $this->assertSame($rowid, $result['addedrowid']);

        $tasks = \core\task\manager::get_adhoc_tasks(\local_tituscontentlibrary\task\add_content_task::class);
        $this->assertCount(0, $tasks);
    }

    /**
     * A user without the addcontent capability must get a required_capability_exception.
     */
    public function test_requires_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        add_course::execute('content_xyz');
    }

    /**
     * When mod_scorm is disabled the function must throw moodle_exception(error:modscormdisabled).
     */
    public function test_modscorm_disabled_throws_exception(): void {
        global $DB;

        $user = $this->make_teacher();
        $this->setUser($user);

        // Disable mod_scorm by setting its enabled flag to 0 in the DB.
        $DB->set_field('modules', 'visible', 0, ['name' => 'scorm']);

        // Force plugin manager to rebuild its cache.
        \core_plugin_manager::reset_caches();

        try {
            add_course::execute('content_xyz');
            $this->fail('Expected moodle_exception not thrown');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:modscormdisabled', $e->errorcode);
        } finally {
            // Restore so other tests are not affected.
            $DB->set_field('modules', 'visible', 1, ['name' => 'scorm']);
            \core_plugin_manager::reset_caches();
        }
    }

    // -----------------------------------------------------------------------
    // Sync mode tests (MLFR-247)
    // -----------------------------------------------------------------------

    /**
     * Build a mock API client that downloads a valid SCORM fixture.
     */
    private function make_sync_mock(string $contentid): titus_api_client_interface {
        global $CFG;
        $source = $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip';

        $dto = new content_dto(
            id: $contentid,
            title: 'Sync Test ' . $contentid,
            description: 'Sync test description',
            short_description: '',
            thumbnail_url: '',
            category: 'Test',
            tags: [],
            duration_minutes: 5,
            is_featured: false,
            is_new: false,
        );

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));
        $mock->method('download_to')
             ->willReturnCallback(function (string $url, string $dest) use ($source): void {
                 copy($source, $dest);
             });
        return $mock;
    }

    /**
     * Sync mode: execute() creates the course immediately and returns course_url.
     */
    public function test_sync_mode_creates_course_and_returns_course_url(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        set_config('addmode', 'sync', 'local_tituscontentlibrary');

        $user = $this->make_teacher();
        $this->setUser($user);
        $cid  = 'sync_happy';

        client_factory::set_test_client($this->make_sync_mock($cid));

        $result = add_course::execute($cid);

        $this->assertFalse($result['queued']);
        $this->assertFalse($result['alreadyqueued']);
        $this->assertSame(content_status::COMPLETED, $result['status']);
        $this->assertStringContainsString('/course/view.php?id=', $result['course_url']);
        $this->assertNull($result['errormessage']);

        // DB row must be completed with real ids.
        $row = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $cid], '*', MUST_EXIST);
        $this->assertSame(content_status::COMPLETED, $row->status);
        $this->assertGreaterThan(0, (int)$row->courseid);
        $this->assertGreaterThan(0, (int)$row->scormid);

        // Course and SCORM must exist in DB.
        $this->assertTrue($DB->record_exists('course', ['id' => $row->courseid]));
        $this->assertTrue($DB->record_exists('scorm',  ['id' => $row->scormid]));

        // No adhoc task should have been queued.
        $tasks = \core\task\manager::get_adhoc_tasks(\local_tituscontentlibrary\task\add_content_task::class);
        $this->assertCount(0, $tasks);
    }

    /**
     * Sync mode: a failed row is reset and re-imported synchronously.
     */
    public function test_sync_mode_retries_failed_row(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        set_config('addmode', 'sync', 'local_tituscontentlibrary');

        $user = $this->make_teacher();
        $this->setUser($user);
        $cid  = 'sync_retry';

        $now   = time();
        $rowid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => $cid,
            'addedby'      => $user->id,
            'status'       => content_status::FAILED,
            'errormessage' => 'Previous failure',
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        client_factory::set_test_client($this->make_sync_mock($cid));

        $result = add_course::execute($cid);

        $this->assertSame(content_status::COMPLETED, $result['status']);
        $this->assertSame($rowid, $result['addedrowid']);
        $this->assertStringContainsString('/course/view.php?id=', $result['course_url']);

        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid], '*', MUST_EXIST);
        $this->assertSame(content_status::COMPLETED, $row->status);
        $this->assertNull($row->errormessage);
    }

    /**
     * Sync mode: an importer failure returns status=failed with errormessage, no task queued.
     */
    public function test_sync_mode_failure_returns_failed_status(): void {
        global $DB;

        set_config('addmode', 'sync', 'local_tituscontentlibrary');

        $user = $this->make_teacher();
        $this->setUser($user);
        $cid  = 'sync_fail';

        $dto = new content_dto(
            id: $cid, title: 'Fail', description: '', short_description: '',
            thumbnail_url: '', category: '', tags: [], duration_minutes: 0,
            is_featured: false, is_new: false,
        );
        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));
        $mock->method('download_to')
             ->willThrowException(new \RuntimeException('Simulated download error'));
        client_factory::set_test_client($mock);

        $result = add_course::execute($cid);

        $this->assertFalse($result['queued']);
        $this->assertSame(content_status::FAILED, $result['status']);
        $this->assertNull($result['course_url']);
        $this->assertStringContainsString('Simulated download error', $result['errormessage']);

        // Row must exist and be marked failed.
        $row = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $cid], '*', MUST_EXIST);
        $this->assertSame(content_status::FAILED, $row->status);
        $this->assertNotEmpty($row->errormessage);

        // No adhoc task.
        $tasks = \core\task\manager::get_adhoc_tasks(\local_tituscontentlibrary\task\add_content_task::class);
        $this->assertCount(0, $tasks);
    }

    /**
     * Sync mode: content_added event is fired on success.
     */
    public function test_sync_mode_fires_content_added_event(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        set_config('addmode', 'sync', 'local_tituscontentlibrary');

        $user = $this->make_teacher();
        $this->setUser($user);
        $cid  = 'sync_event';

        client_factory::set_test_client($this->make_sync_mock($cid));

        $sink = $this->redirectEvents();
        add_course::execute($cid);
        $events = $sink->get_events();
        $sink->close();

        $eventnames = array_map(fn($e) => $e->eventname, $events);
        $this->assertContains('\local_tituscontentlibrary\event\content_added', $eventnames);
    }

    /**
     * The alreadyqueued completed path returns course_url from the existing row.
     */
    public function test_completed_alreadyqueued_returns_course_url(): void {
        global $DB, $CFG;

        $user = $this->make_teacher();
        $this->setUser($user);

        $course = $this->getDataGenerator()->create_course();
        $now    = time();
        $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'content_done_url',
            'addedby'      => $user->id,
            'status'       => content_status::COMPLETED,
            'courseid'     => $course->id,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);

        $result = add_course::execute('content_done_url');

        $this->assertTrue($result['alreadyqueued']);
        $this->assertSame(content_status::COMPLETED, $result['status']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $result['course_url']);
    }
}
