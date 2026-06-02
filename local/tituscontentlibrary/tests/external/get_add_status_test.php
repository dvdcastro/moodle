<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\external\get_add_status
 */
class get_add_status_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    private function give_view_cap(\stdClass $user): void {
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
    }

    private function insert_added_row(array $fields): int {
        global $DB;
        $defaults = [
            'contentid'    => 'sim-001',
            'courseid'     => null,
            'cmid'         => null,
            'addedby'      => 2,
            'status'       => 'pending',
            'errormessage' => null,
            'timecreated'  => time(),
            'timemodified' => 0,
        ];
        return $DB->insert_record('local_tituscontentlibrary_added', array_merge($defaults, $fields));
    }

    public function test_returns_notqueued_for_unknown_content(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->give_view_cap($user);
        $this->setUser($user);

        $result = get_add_status::execute('nonexistent-content-id');

        $this->assertEquals('notqueued', $result['status']);
        $this->assertNull($result['course_url']);
        $this->assertNull($result['errormessage']);
    }

    public function test_returns_pending_after_insert(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->give_view_cap($user);
        $this->setUser($user);

        $this->insert_added_row(['contentid' => 'sim-pending', 'addedby' => $user->id, 'status' => 'pending']);

        $result = get_add_status::execute('sim-pending');

        $this->assertEquals('pending', $result['status']);
        $this->assertNull($result['course_url']);
    }

    public function test_returns_completed_with_course_url(): void {
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->give_view_cap($user);
        $this->setUser($user);

        $this->insert_added_row([
            'contentid' => 'sim-done',
            'addedby'   => $user->id,
            'status'    => 'completed',
            'courseid'  => $course->id,
        ]);

        $result = get_add_status::execute('sim-done');

        $this->assertEquals('completed', $result['status']);
        $this->assertNotNull($result['course_url']);
        $this->assertStringContainsString('/course/view.php?id=' . $course->id, $result['course_url']);
    }

    public function test_returns_failed_with_errormessage(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->give_view_cap($user);
        $this->setUser($user);

        $this->insert_added_row([
            'contentid'    => 'sim-fail',
            'addedby'      => $user->id,
            'status'       => 'failed',
            'errormessage' => 'SCORM package invalid',
        ]);

        $result = get_add_status::execute('sim-fail');

        $this->assertEquals('failed', $result['status']);
        $this->assertEquals('SCORM package invalid', $result['errormessage']);
    }

    public function test_throws_without_view_cap(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_add_status::execute('sim-001');
    }

    public function test_errormessage_truncated_at_500_chars(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->give_view_cap($user);
        $this->setUser($user);

        $longmsg = str_repeat('a', 600);
        $this->insert_added_row([
            'contentid'    => 'sim-truncate',
            'addedby'      => $user->id,
            'status'       => 'failed',
            'errormessage' => $longmsg,
        ]);

        $result = get_add_status::execute('sim-truncate');

        $this->assertEquals(500, \core_text::strlen($result['errormessage']));
    }
}
