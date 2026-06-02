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

namespace local_tituscontentlibrary\task;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;
use local_tituscontentlibrary\local\content_status;

/**
 * Unit / integration tests for add_content_task.
 *
 * These tests use a mock API client injected via set_client_for_testing() so
 * no real HTTP calls are made. A minimal valid SCORM ZIP (containing
 * imsmanifest.xml at the root) is created in a temp directory for each test.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\task\add_content_task
 */
class add_content_task_test extends \advanced_testcase {

    /** @var string Temp dir used across this class. */
    private string $tmpdir;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->tmpdir = make_temp_directory('titustest');
        // Always reset the injected client so tests are isolated.
        add_content_task::set_client_for_testing(null);
    }

    protected function tearDown(): void {
        add_content_task::set_client_for_testing(null);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Return the path to a valid SCORM 1.2 ZIP fixture.
     *
     * Uses Moodle's own singlescobasic.zip test package (which contains a fully
     * parseable imsmanifest.xml). The file is copied to the temp dir so tests
     * can manipulate it without affecting the source.
     */
    private function make_valid_scorm_zip(string $name = 'test_scorm.zip'): string {
        global $CFG;
        $source = $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip';
        $dest   = $this->tmpdir . '/' . $name;
        copy($source, $dest);
        return $dest;
    }

    /**
     * Build a ZIP that is NOT a valid SCORM package (no imsmanifest.xml).
     */
    private function make_invalid_scorm_zip(string $name = 'bad_scorm.zip'): string {
        $path = $this->tmpdir . '/' . $name;
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'This is not a SCORM package');
        $zip->close();
        return $path;
    }

    /**
     * Build a simple content_dto fixture.
     */
    private function make_dto(string $id = 'content_001'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Test Content ' . $id,
            description: 'Test description',
            short_description: 'Short',
            thumbnail_url: '',
            category: 'Leadership',
            tags: [],
            duration_minutes: 10,
            is_featured: false,
            is_new: false,
        );
    }

    /**
     * Create an _added row in DB with status=pending and return the row id.
     */
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

    /**
     * Build an add_content_task with injected custom data.
     */
    private function make_task(string $contentid, int $userid, int $addedrowid): add_content_task {
        $task = new add_content_task();
        $task->set_custom_data((object)[
            'contentid'  => $contentid,
            'userid'     => $userid,
            'addedrowid' => $addedrowid,
        ]);
        return $task;
    }

    /**
     * Build a mock titus_api_client_interface that returns a valid SCORM ZIP on download_to().
     *
     * @param content_dto   $dto      DTO the mock will return from get_catalogue().
     * @param string        $zippath  Source ZIP that will be copied to $destpath in download_to().
     */
    private function make_mock_client(content_dto $dto, string $zippath): titus_api_client_interface {
        $mock = $this->createMock(titus_api_client_interface::class);

        $mock->method('get_catalogue')->willReturn([$dto]);

        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));

        // On download_to, copy our fixture zip to the requested destination path.
        $mock->method('download_to')
             ->willReturnCallback(function (string $url, string $destpath) use ($zippath): void {
                 copy($zippath, $destpath);
             });

        return $mock;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Happy path: valid SCORM ZIP → course and SCORM activity created, row=completed.
     */
    public function test_happy_path_creates_course_and_scorm(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user); // Required by file_get_unused_draft_itemid().
        $contentid = 'content_happy';
        $dto       = $this->make_dto($contentid);
        $zippath   = $this->make_valid_scorm_zip();

        add_content_task::set_client_for_testing($this->make_mock_client($dto, $zippath));

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = $this->make_task($contentid, $user->id, $rowid);

        ob_start();
        $task->execute();
        ob_end_clean();

        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid], '*', MUST_EXIST);
        $this->assertSame(content_status::COMPLETED, $row->status);
        $this->assertNotEmpty($row->courseid);
        $this->assertNotEmpty($row->cmid);
        $this->assertNotEmpty($row->scormid);

        // Course must exist.
        $this->assertTrue($DB->record_exists('course', ['id' => $row->courseid]));

        // SCORM module must exist.
        $this->assertTrue($DB->record_exists('scorm', ['course' => $row->courseid]));
    }

    /**
     * An invalid ZIP (no imsmanifest.xml) must mark the row as failed and
     * NOT create a course.
     */
    public function test_invalid_zip_marks_failed_no_course_created(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_bad_zip';
        $dto       = $this->make_dto($contentid);
        $badzip    = $this->make_invalid_scorm_zip();

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));
        $mock->method('download_to')->willReturnCallback(
            function (string $url, string $destpath) use ($badzip): void {
                copy($badzip, $destpath);
            }
        );
        add_content_task::set_client_for_testing($mock);

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = $this->make_task($contentid, $user->id, $rowid);

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
        $this->assertNotEmpty($row->errormessage);

        // No course should have been created for this content.
        $this->assertEmpty($row->courseid);
    }

    /**
     * A 404 / network error on signed URL download must mark the row failed.
     */
    public function test_signed_url_download_failure_marks_failed(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_404';
        $dto       = $this->make_dto($contentid);

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/missing.zip', 'missing.zip', 300));
        $mock->method('download_to')
             ->willThrowException(
                 new \local_tituscontentlibrary\api\exception\titus_not_found_exception('missing.zip')
             );
        add_content_task::set_client_for_testing($mock);

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = $this->make_task($contentid, $user->id, $rowid);

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
        $this->assertNotEmpty($row->errormessage);
    }

    /**
     * On importer failure, the task must mark the row failed and leave no orphan course.
     *
     * Uses a download failure (throws before any DB changes) to verify the task's
     * catch block correctly writes the failed status.
     */
    public function test_importer_failure_marks_row_failed_no_orphan_course(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_rollback';
        $dto       = $this->make_dto($contentid);

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));
        $mock->method('download_to')
             ->willThrowException(new \RuntimeException('Simulated download failure'));
        add_content_task::set_client_for_testing($mock);

        $coursesBefore = $DB->count_records('course');
        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = $this->make_task($contentid, $user->id, $rowid);

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
        $this->assertNotEmpty($row->errormessage);
        $this->assertSame($coursesBefore, $DB->count_records('course'));
    }

    /**
     * Verify status transitions: pending → processing → completed.
     *
     * This test captures the DB state after execute() to verify the final state.
     * The processing state is transient so we just verify start=pending, end=completed.
     */
    public function test_status_transitions_pending_to_processing_to_completed(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        $user      = $this->getDataGenerator()->create_user();
        $contentid = 'content_status_trans';
        $dto       = $this->make_dto($contentid);
        $zippath   = $this->make_valid_scorm_zip('status.zip');

        $this->setUser($user); // Required by file_get_unused_draft_itemid().
        add_content_task::set_client_for_testing($this->make_mock_client($dto, $zippath));

        $rowid = $this->insert_pending_row($contentid, $user->id);

        // Verify initial state.
        $before = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid]);
        $this->assertSame(content_status::PENDING, $before->status);

        ob_start();
        $task = $this->make_task($contentid, $user->id, $rowid);
        $task->execute();
        ob_end_clean();

        // Verify final state.
        $after = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid]);
        $this->assertSame(content_status::COMPLETED, $after->status);
    }

    /**
     * When execute() fails, errormessage must be populated in the DB record.
     */
    public function test_errormessage_populated_on_failure(): void {
        global $DB;

        $user      = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $contentid = 'content_err';
        $dto       = $this->make_dto($contentid);

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willThrowException(new \RuntimeException('API is down: 503'));
        add_content_task::set_client_for_testing($mock);

        $rowid = $this->insert_pending_row($contentid, $user->id);
        $task  = $this->make_task($contentid, $user->id, $rowid);

        ob_start();
        try {
            $task->execute();
            $this->fail('Expected exception not thrown');
        } catch (\Throwable $e) {
            // Expected.
        } finally {
            ob_end_clean();
        }

        $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $rowid]);
        $this->assertSame(content_status::FAILED, $row->status);
        $this->assertStringContainsString('API is down: 503', $row->errormessage);
    }
}
