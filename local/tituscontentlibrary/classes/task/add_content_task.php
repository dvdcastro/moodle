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

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\exception\titus_rate_limit_exception;
use local_tituscontentlibrary\local\content_importer;
use local_tituscontentlibrary\local\content_status;

/**
 * Adhoc task: download a Titus SCORM package and create a Moodle course.
 *
 * Delegates the download → validate → course/SCORM pipeline to content_importer.
 * This task owns the _added row status transitions and event firing only.
 *
 * Status transitions: pending → processing → completed | failed
 * On any failure the DB record is marked failed (outside the transaction so
 * the error is persisted), and the exception is re-thrown so Moodle's cron
 * engine can log and retry the task automatically.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_content_task extends \core\task\adhoc_task {

    /**
     * Inject an API client for unit testing. Delegates to client_factory.
     *
     * @param titus_api_client_interface|null $client
     */
    public static function set_client_for_testing(?titus_api_client_interface $client): void {
        client_factory::set_test_client($client);
    }

    public function get_name(): string {
        return get_string('task:addcontent', 'local_tituscontentlibrary');
    }

    public function execute(): void {
        global $DB;

        $data       = $this->get_custom_data();
        $contentid  = $data->contentid;
        $userid     = $data->userid;
        $addedrowid = $data->addedrowid;

        try {
            // 1. Load row and set to processing.
            $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid], '*', MUST_EXIST);
            $DB->update_record('local_tituscontentlibrary_added', (object)[
                'id'           => $addedrowid,
                'status'       => content_status::PROCESSING,
                'timemodified' => time(),
            ]);

            // 2–8. Run the shared download + course + SCORM pipeline.
            $result = (new content_importer())->import($contentid, $userid);

            // 9. Update _added record with success.
            $DB->update_record('local_tituscontentlibrary_added', (object)[
                'id'           => $addedrowid,
                'courseid'     => $result->courseid,
                'cmid'         => $result->cmid,
                'scormid'      => $result->scormid,
                'status'       => content_status::COMPLETED,
                'errormessage' => null,
                'timemodified' => time(),
            ]);

            mtrace('[titus] add_content_task completed: ' . $contentid . ' → course ' . $result->courseid);

            \local_tituscontentlibrary\event\content_added::create_event(
                $result->courseid,
                $contentid,
                $userid,
                $addedrowid
            )->trigger();

        } catch (titus_rate_limit_exception $e) {
            // 429: keep row PENDING so the rescheduled task can retry.
            try {
                $DB->update_record('local_tituscontentlibrary_added', (object)[
                    'id'           => $addedrowid,
                    'status'       => content_status::PENDING,
                    'timemodified' => time(),
                ]);
            } catch (\Throwable $ue) {
                debugging('[titus] failed to reset status to pending: ' . $ue->getMessage(), DEBUG_DEVELOPER);
            }
            $this->set_next_run_time(time() + max(1, $e->retry_after));
            throw $e;
        } catch (\Throwable $e) {
            // Mark as failed — only the message, no stack trace in the DB record.
            try {
                $DB->update_record('local_tituscontentlibrary_added', (object)[
                    'id'           => $addedrowid,
                    'status'       => content_status::FAILED,
                    'errormessage' => $e->getMessage(),
                    'timemodified' => time(),
                ]);
            } catch (\Throwable $ue) {
                debugging('[titus] failed to update error status: ' . $ue->getMessage(), DEBUG_DEVELOPER);
            }
            try {
                \local_tituscontentlibrary\event\content_add_failed::create_event(
                    $contentid,
                    $userid,
                    $e->getMessage()
                )->trigger();
            } catch (\Throwable $ee) {
                debugging('[titus] failed to fire content_add_failed event: ' . $ee->getMessage(), DEBUG_DEVELOPER);
            }
            throw $e;
        }
    }
}
