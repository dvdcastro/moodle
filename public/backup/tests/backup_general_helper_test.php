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

use backup_general_helper;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

/**
 * Backup general helper tests.
 *
 * @package    core_backup
 * @copyright  2025 Frederic Massart <fred@branchup.tech>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class backup_general_helper_test extends \advanced_testcase {

    /**
     * Tests the behaviour with non checksumable objects.
     *
     * @covers backup_general_helper::array_checksum_recursive
     */
    public function test_array_checksum_recursive_with_non_checksumable_objects(): void {
        $parent  = new class() {
            protected $child;
            public function set_child($child) {
                $this->child = $child;
            }
        };
        $child = new class($parent) {
            public function __construct(protected $parent) {
                $parent->set_child($this);
            }
        };
        $expectedchecksum = md5('-0-' . $parent::class);
        $this->assertEquals($expectedchecksum, backup_general_helper::array_checksum_recursive([$parent]));

        $expectedchecksum = md5('-0-' . $child::class);
        $this->assertEquals($expectedchecksum, backup_general_helper::array_checksum_recursive([$child]));

        $expectedchecksum = md5(md5('-0-' . $parent::class) . '-1-' . $child::class);
        $this->assertEquals($expectedchecksum, backup_general_helper::array_checksum_recursive([$parent, $child]));
    }

}
