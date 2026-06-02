<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\local\content_status;

/**
 * @covers \local_tituscontentlibrary\external\resync_course
 */
class resync_course_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    private function set_user_with_manage_cap(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:manageplugin', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        return $user;
    }

    private function insert_added_row(string $status, int $courseid = 1, int $cmid = 1): int {
        global $DB, $USER;
        return $DB->insert_record('local_tituscontentlibrary_added', (object)[
            'contentid'    => 'sim-001',
            'courseid'     => $courseid,
            'cmid'         => $cmid,
            'addedby'      => $USER->id ?? 2,
            'status'       => $status,
            'errormessage' => null,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_queues_resync_for_completed_row(): void {
        $this->set_user_with_manage_cap();
        $this->insert_added_row(content_status::COMPLETED, 1, 1);

        $result = resync_course::execute('sim-001');

        $this->assertTrue($result['queued']);
        global $DB;
        $row = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => 'sim-001']);
        $this->assertSame(content_status::PENDING, $row->status);
    }

    public function test_rejects_pending_row(): void {
        $this->set_user_with_manage_cap();
        $this->insert_added_row(content_status::PENDING, 1, 1);

        $this->expectException(\moodle_exception::class);
        resync_course::execute('sim-001');
    }

    public function test_rejects_failed_row(): void {
        $this->set_user_with_manage_cap();
        $this->insert_added_row(content_status::FAILED, 1, 1);

        $this->expectException(\moodle_exception::class);
        resync_course::execute('sim-001');
    }

    public function test_rejects_when_courseid_zero(): void {
        $this->set_user_with_manage_cap();
        $this->insert_added_row(content_status::COMPLETED, 0, 0);

        $this->expectException(\moodle_exception::class);
        resync_course::execute('sim-001');
    }

    public function test_requires_manage_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->insert_added_row(content_status::COMPLETED, 1, 1);

        $this->expectException(\required_capability_exception::class);
        resync_course::execute('sim-001');
    }

    public function test_throws_for_nonexistent_content(): void {
        $this->set_user_with_manage_cap();

        $this->expectException(\dml_missing_record_exception::class);
        resync_course::execute('nonexistent-id');
    }
}
