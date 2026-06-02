<?php
namespace local_tituscontentlibrary\hook\navigation;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\hook\navigation\extend_primary_nav
 */
class extend_primary_nav_test extends \advanced_testcase {

    protected function setUp(): void {
        global $PAGE;
        parent::setUp();
        $this->resetAfterTest(true);
        $PAGE = new \moodle_page();
        $PAGE->set_url('/');
    }

    /**
     * Fire the hook callback and return the primaryview after the callback ran.
     */
    private function invoke_callback(): \core\navigation\views\primary {
        global $PAGE;
        $primarynav = new \core\navigation\views\primary($PAGE);
        $hook       = new \core\hook\navigation\primary_extend($primarynav);
        extend_primary_nav::callback($hook);
        return $primarynav;
    }

    public function test_adds_nav_node_for_user_with_view_cap(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);

        $primarynav = $this->invoke_callback();
        $this->assertContains('tituscontentlibrary', $primarynav->get_children_key_list());
    }

    public function test_does_not_add_node_without_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $primarynav = $this->invoke_callback();
        $this->assertNotContains('tituscontentlibrary', $primarynav->get_children_key_list());
    }

    public function test_does_not_add_node_for_guests(): void {
        $this->setGuestUser();

        $primarynav = $this->invoke_callback();
        $this->assertNotContains('tituscontentlibrary', $primarynav->get_children_key_list());
    }

    public function test_does_not_add_node_when_not_logged_in(): void {
        $this->setUser(0);

        $primarynav = $this->invoke_callback();
        $this->assertNotContains('tituscontentlibrary', $primarynav->get_children_key_list());
    }
}
