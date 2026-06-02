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
use core_external\external_value;
use core_external\external_single_structure;
use local_tituscontentlibrary\config;
use local_tituscontentlibrary\local\content_importer;
use local_tituscontentlibrary\local\content_status;
use local_tituscontentlibrary\task\add_content_task;

/**
 * External function: import a Titus content item as a Moodle course.
 *
 * Supports two modes controlled by the 'addmode' plugin setting:
 *  - async (default): queues an adhoc task; tile polls for progress.
 *  - sync: runs the full import pipeline in the web request; returns course_url immediately.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_course extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contentid' => new external_value(PARAM_ALPHANUMEXT, 'Titus content ID'),
        ]);
    }

    /**
     * Queue or synchronously import a Titus content item.
     *
     * @param string $contentid Titus content identifier.
     * @return array
     */
    public static function execute(string $contentid): array {
        global $DB, $USER, $CFG;

        $params    = self::validate_parameters(self::execute_parameters(), ['contentid' => $contentid]);
        $contentid = $params['contentid'];

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/tituscontentlibrary:addcourse', $ctx);

        $pluginman = \core_plugin_manager::instance();
        $scorminfo = $pluginman->get_plugin_info('mod_scorm');
        if (!$scorminfo || $scorminfo->is_enabled() === false) {
            throw new \moodle_exception('error:modscormdisabled', 'local_tituscontentlibrary');
        }

        $now  = time();
        $mode = config::get_add_mode();

        $existing = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $contentid]);
        if ($existing) {
            if ($existing->status === content_status::FAILED) {
                // Reset to pending so the import can be re-attempted.
                $existing->status       = content_status::PENDING;
                $existing->errormessage = null;
                $existing->timemodified = $now;
                $DB->update_record('local_tituscontentlibrary_added', $existing);

                if ($mode === config::ADDMODE_SYNC) {
                    return self::import_sync($contentid, (int)$USER->id, (int)$existing->id);
                }

                self::queue_task($contentid, (int)$USER->id, (int)$existing->id);
                return [
                    'queued'        => true,
                    'alreadyqueued' => false,
                    'addedrowid'    => (int)$existing->id,
                    'status'        => content_status::PENDING,
                    'course_url'    => null,
                    'errormessage'  => null,
                ];
            }

            // pending / processing / completed — nothing new to start.
            $course_url = ($existing->status === content_status::COMPLETED && !empty($existing->courseid))
                ? $CFG->wwwroot . '/course/view.php?id=' . $existing->courseid
                : null;
            return [
                'queued'        => false,
                'alreadyqueued' => true,
                'addedrowid'    => (int)$existing->id,
                'status'        => $existing->status,
                'course_url'    => $course_url,
                'errormessage'  => null,
            ];
        }

        // No record yet — INSERT. The UNIQUE constraint on contentid guards
        // against a concurrent duplicate insert.
        try {
            $id = $DB->insert_record('local_tituscontentlibrary_added', (object)[
                'contentid'    => $contentid,
                'addedby'      => $USER->id,
                'status'       => content_status::PENDING,
                'timecreated'  => $now,
                'timemodified' => $now,
            ]);
        } catch (\dml_write_exception $e) {
            // Lost a race with a concurrent request — treat as already queued.
            $existing = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $contentid]);
            $course_url = ($existing && $existing->status === content_status::COMPLETED && !empty($existing->courseid))
                ? $CFG->wwwroot . '/course/view.php?id=' . $existing->courseid
                : null;
            return [
                'queued'        => false,
                'alreadyqueued' => true,
                'addedrowid'    => $existing ? (int)$existing->id : 0,
                'status'        => $existing ? $existing->status : '',
                'course_url'    => $course_url,
                'errormessage'  => null,
            ];
        }

        if ($mode === config::ADDMODE_SYNC) {
            return self::import_sync($contentid, (int)$USER->id, (int)$id);
        }

        self::queue_task($contentid, (int)$USER->id, (int)$id);
        return [
            'queued'        => true,
            'alreadyqueued' => false,
            'addedrowid'    => (int)$id,
            'status'        => content_status::PENDING,
            'course_url'    => null,
            'errormessage'  => null,
        ];
    }

    /**
     * Run the import pipeline synchronously and return a structured result.
     *
     * Never throws — failures are returned as status=failed with errormessage,
     * consistent with the tile.js failed-state handler.
     *
     * @param string $contentid  Titus content identifier.
     * @param int    $userid     User performing the import.
     * @param int    $addedrowid Row ID in local_tituscontentlibrary_added.
     * @return array
     */
    private static function import_sync(string $contentid, int $userid, int $addedrowid): array {
        global $CFG, $DB;

        $DB->update_record('local_tituscontentlibrary_added', (object)[
            'id'           => $addedrowid,
            'status'       => content_status::PROCESSING,
            'timemodified' => time(),
        ]);

        try {
            $result = (new content_importer())->import($contentid, $userid);

            $DB->update_record('local_tituscontentlibrary_added', (object)[
                'id'           => $addedrowid,
                'courseid'     => $result->courseid,
                'cmid'         => $result->cmid,
                'scormid'      => $result->scormid,
                'status'       => content_status::COMPLETED,
                'errormessage' => null,
                'timemodified' => time(),
            ]);

            \local_tituscontentlibrary\event\content_added::create_event(
                $result->courseid,
                $contentid,
                $userid,
                $addedrowid
            )->trigger();

            return [
                'queued'        => false,
                'alreadyqueued' => false,
                'addedrowid'    => $addedrowid,
                'status'        => content_status::COMPLETED,
                'course_url'    => $CFG->wwwroot . '/course/view.php?id=' . $result->courseid,
                'errormessage'  => null,
            ];

        } catch (\Throwable $e) {
            try {
                $DB->update_record('local_tituscontentlibrary_added', (object)[
                    'id'           => $addedrowid,
                    'status'       => content_status::FAILED,
                    'errormessage' => $e->getMessage(),
                    'timemodified' => time(),
                ]);
            } catch (\Throwable $ue) {
                debugging('[titus] sync import: failed to update error status: ' . $ue->getMessage(), DEBUG_DEVELOPER);
            }
            try {
                \local_tituscontentlibrary\event\content_add_failed::create_event(
                    $contentid,
                    $userid,
                    $e->getMessage()
                )->trigger();
            } catch (\Throwable $ee) {
                debugging('[titus] sync import: failed to fire content_add_failed: ' . $ee->getMessage(), DEBUG_DEVELOPER);
            }
            return [
                'queued'        => false,
                'alreadyqueued' => false,
                'addedrowid'    => $addedrowid,
                'status'        => content_status::FAILED,
                'course_url'    => null,
                'errormessage'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Queue the adhoc task that performs the actual import.
     *
     * @param string $contentid  Titus content identifier.
     * @param int    $userid     User performing the add.
     * @param int    $addedrowid Row ID in local_tituscontentlibrary_added.
     */
    protected static function queue_task(string $contentid, int $userid, int $addedrowid): void {
        $task = new add_content_task();
        $task->set_custom_data((object)[
            'contentid'  => $contentid,
            'userid'     => $userid,
            'addedrowid' => $addedrowid,
        ]);
        $task->set_userid($userid);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'queued'        => new external_value(PARAM_BOOL, 'Whether a new task was queued'),
            'alreadyqueued' => new external_value(PARAM_BOOL, 'Whether it was already in queue'),
            'addedrowid'    => new external_value(PARAM_INT,  'Row ID in local_tituscontentlibrary_added'),
            'status'        => new external_value(PARAM_TEXT, 'Current status'),
            'course_url'    => new external_value(PARAM_TEXT, 'Course URL when created synchronously', VALUE_DEFAULT, null, NULL_ALLOWED),
            'errormessage'  => new external_value(PARAM_TEXT, 'Error message when sync import failed',  VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }
}
