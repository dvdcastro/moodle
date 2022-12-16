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

namespace mod_url;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/url/locallib.php');

/**
 * mod_url tests for local_lib
 *
 * @package    mod_url
 * @copyright  2023 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class locallib_test extends \advanced_testcase {

    /**
     * Config for url activity.
     * @var \stdClass
     */
    private $config;

    /**
     * Test set up.
     *
     * This is executed before running any test in this file.
     */
    public function setUp(): void {
        $this->config = new \stdClass();
        $this->config->rolesinparams = false;
        $this->config->userprofilefields = false;
    }

    /**
     * Tests that group names are included in options.
     * @covers ::url_get_variable_options
     */
    public function test_group_names_variable_options() {
        $this->resetAfterTest();

        $options = url_get_variable_options($this->config);
        $this->assertArrayHasKey('groupnames', $options[get_string('course')]);
    }

    /**
     * Tests that group names are included in values.
     * @covers ::url_get_variable_values
     */
    public function test_group_names_variable_values() {
        global $DB;
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $user = $generator->create_user();
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $generator->enrol_user($user->id, $course->id, $studentroleid);

        for ($i = 1; $i <= 3; $i++) {
            $group = $generator->create_group(['courseid' => $course->id, 'name' => "Group $i"]);
            groups_add_member($group, $user);
        }

        $this->setUser($user);

        $url = $generator->create_module('url', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('url', $url->id);
        $values = url_get_variable_values($url, $cm, $course, $this->config);

        $this->assertSame(['Group 1', 'Group 2', 'Group 3'], $values['groupnames']);
    }

    /**
     * Tests that options are not included if setting is not present.
     * @covers ::url_get_variable_options
     */
    public function test_user_profile_fields_disabled() {
        $this->resetAfterTest();
        $this->config->userprofilefields = false;
        $options = url_get_variable_options($this->config);
        $this->assertArrayNotHasKey(get_string('profilefields', 'admin'), $options);
    }

    /**
     * Tests that options are included if setting is present.
     * @covers ::url_get_variable_options
     */
    public function test_user_profile_fields_variable_options() {
        $this->resetAfterTest();
        $this->config->userprofilefields = true;
        $this->getDataGenerator()->create_custom_profile_field(['shortname' => 'muggleborn', 'name' => 'Muggle-born',
                'required' => 1, 'visible' => 1, 'locked' => 1, 'datatype' => 'checkbox']);
        $options = url_get_variable_options($this->config);
        $profileoptions = $options[get_string('profilefields', 'admin')];
        $this->assertArrayHasKey('user_profile_muggleborn', $profileoptions);
    }

    /**
     * Tests that values are included for user profile fields.
     * @covers ::url_get_variable_values
     */
    public function test_user_profile_fields_variable_values() {
        global $DB;
        $this->resetAfterTest();
        $this->config->userprofilefields = true;

        $generator = $this->getDataGenerator();
        $generator->create_custom_profile_field(['shortname' => 'muggleborn', 'name' => 'Muggle-born',
                'required' => 1, 'visible' => 1, 'locked' => 1, 'datatype' => 'checkbox']);
        $course = $generator->create_course();
        $user = $generator->create_user();
        $studentroleid = $DB->get_field('role', 'id', ['shortname' => 'student']);
        $generator->enrol_user($user->id, $course->id, $studentroleid);

        $this->setUser($user);

        $url = $generator->create_module('url', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('url', $url->id);
        $values = url_get_variable_values($url, $cm, $course, $this->config);

        $this->assertEquals(0, $values['user_profile_muggleborn']);
    }
}
