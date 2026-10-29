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

namespace core_backup;

use backup;
use backup_controller;
use restore_controller;
use restore_dbops;

/**
 * Tests for user conflicts found by the restore precheck.
 *
 * @package    core_backup
 * @category   test
 * @copyright  2026 Louisiana State University
 * @author     David Castro
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \restore_dbops::precheck_included_users
 */
final class restore_user_conflict_test extends \advanced_testcase {
    /**
     * Load required libraries.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');
        parent::setUpBeforeClass();
    }

    /**
     * Data provider for {@see test_conflicting_user_is_reported}.
     *
     * @return array
     */
    public static function duplicate_admin_allowed_provider(): array {
        return [
            'Duplicate admin not allowed' => [false],
            'Duplicate admin allowed' => [true],
        ];
    }

    /**
     * A conflicting user (not 'admin') must be reported by the precheck, whatever the value of
     * import_general_duplicate_admin_allowed, and must never be created.
     *
     * @dataProvider duplicate_admin_allowed_provider
     * @param bool $duplicateadminallowed value of backup | import_general_duplicate_admin_allowed
     */
    public function test_conflicting_user_is_reported(bool $duplicateadminallowed): void {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $student = $generator->create_and_enrol($course, 'student', ['username' => 'conflictuser']);

        // Back up the course, with users.
        $bc = new backup_controller(
            backup::TYPE_1COURSE,
            $course->id,
            backup::FORMAT_MOODLE,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        // The user in the backup is renamed, and another user takes the username: on a same-site
        // restore, the username now belongs to a different id (a conflict).
        $DB->set_field('user', 'username', 'conflictuser_renamed', ['id' => $student->id]);
        $otheruser = $generator->create_user(['username' => 'conflictuser']);

        set_config('import_general_duplicate_admin_allowed', $duplicateadminallowed, 'backup');

        $tempdir = restore_controller::get_tempdir_name(SITEID, $USER->id);
        $backupfile->extract_to_pathname(
            get_file_packer('application/vnd.moodle.backup'),
            make_backup_temp_directory($tempdir)
        );
        $newcourseid = restore_dbops::create_new_course('Restored', 'R1', $course->category);
        $rc = new restore_controller(
            $tempdir,
            $newcourseid,
            backup::INTERACTIVE_NO,
            backup::MODE_GENERAL,
            $USER->id,
            backup::TARGET_NEW_COURSE
        );

        $this->assertFalse($rc->execute_precheck(true));
        $results = $rc->get_precheck_results();
        $rc->destroy();

        $this->assertContains(get_string('restoreuserconflict', '', 'conflictuser'), $results['errors']);

        // No account was created for the conflicting user.
        $this->assertEquals(
            [$otheruser->id],
            array_keys($DB->get_records('user', ['username' => 'conflictuser']))
        );
        $this->assertFalse($DB->record_exists('user', ['mnethostid' => 0]));
    }
}
