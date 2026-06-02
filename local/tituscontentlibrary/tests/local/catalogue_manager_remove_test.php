<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\local\catalogue_manager::remove_added_row
 */
class catalogue_manager_remove_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_removes_tracking_row(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $rowid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'sim-remove-001',
            'courseid'     => 1,
            'cmid'         => 1,
            'addedby'      => $user->id,
            'status'       => content_status::COMPLETED,
            'errormessage' => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $cm = new catalogue_manager();
        $cm->remove_added_row($rowid);

        $this->assertFalse($DB->record_exists('local_tituscontentlibrary_added', ['id' => $rowid]));
    }

    public function test_does_not_delete_course(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();

        $rowid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'sim-remove-002',
            'courseid'     => $course->id,
            'cmid'         => 1,
            'addedby'      => $user->id,
            'status'       => content_status::COMPLETED,
            'errormessage' => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $cm = new catalogue_manager();
        $cm->remove_added_row($rowid);

        // Tracking row is gone, but course still exists.
        $this->assertFalse($DB->record_exists('local_tituscontentlibrary_added', ['id' => $rowid]));
        $this->assertTrue($DB->record_exists('course', ['id' => $course->id]));
    }
}
