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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_tituscontentlibrary\local\content_status;

/**
 * WS function: queue a re-sync of a previously imported Titus SCORM package.
 *
 * Re-sync downloads and replaces the SCORM package file without deleting the
 * Moodle course, preserving enrolments, grades, and completion records.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resync_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contentid' => new external_value(PARAM_ALPHANUMEXT, 'Titus content identifier'),
        ]);
    }

    public static function execute(string $contentid): array {
        global $DB, $USER;

        ['contentid' => $contentid] = self::validate_parameters(
            self::execute_parameters(), compact('contentid')
        );

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/tituscontentlibrary:manageplugin', $ctx);

        $row = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $contentid], '*', MUST_EXIST);

        if ($row->status !== content_status::COMPLETED) {
            throw new \moodle_exception('error:cannotresync_notcompleted', 'local_tituscontentlibrary');
        }
        if (empty($row->courseid) || empty($row->cmid)) {
            throw new \moodle_exception('error:cannotresync_courseorcmmissing', 'local_tituscontentlibrary');
        }

        // Reset to pending and queue the resync task.
        $DB->update_record('local_tituscontentlibrary_added', (object)[
            'id'           => $row->id,
            'status'       => content_status::PENDING,
            'errormessage' => null,
            'timemodified' => time(),
        ]);

        $task = new \local_tituscontentlibrary\task\resync_content_task();
        $task->set_custom_data([
            'contentid'  => $contentid,
            'addedrowid' => $row->id,
            'userid'     => $USER->id,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);

        return ['queued' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'queued' => new external_value(PARAM_BOOL, 'True if re-sync was queued successfully'),
        ]);
    }
}
