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
use local_tituscontentlibrary\local\content_status;
use local_tituscontentlibrary\local\scorm_zip_validator;

/**
 * Adhoc task: re-download a Titus SCORM package and replace it in-place.
 *
 * Unlike add_content_task, this task does NOT create a new course. It replaces
 * the SCORM package file and re-parses the manifest, preserving the existing
 * courseid, cmid, enrolments, grades, and completion records.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class resync_content_task extends \core\task\adhoc_task {

    public static function set_client_for_testing(?titus_api_client_interface $client): void {
        client_factory::set_test_client($client);
    }

    protected function make_client(): titus_api_client_interface {
        return client_factory::get_client();
    }

    public function get_name(): string {
        return get_string('task:resynccontent', 'local_tituscontentlibrary');
    }

    public function execute(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        require_once($CFG->libdir . '/filelib.php');

        $data       = $this->get_custom_data();
        $contentid  = $data->contentid;
        $addedrowid = $data->addedrowid;
        $tmpzip     = null;
        $transaction = null;

        try {
            $row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid], '*', MUST_EXIST);

            if (empty($row->courseid) || empty($row->cmid)) {
                throw new \moodle_exception('error:cannotresync_courseorcmmissing', 'local_tituscontentlibrary');
            }

            $DB->update_record('local_tituscontentlibrary_added', (object)[
                'id'           => $addedrowid,
                'status'       => content_status::PROCESSING,
                'timemodified' => time(),
            ]);

            // Download the new SCORM ZIP.
            $client = $this->make_client();
            $signed = $client->get_signed_download_url($contentid);
            $tmpzip = make_request_directory() . '/' . $contentid . '.zip';
            $client->download_to($signed->url, $tmpzip);

            scorm_zip_validator::validate($tmpzip);

            $transaction = $DB->start_delegated_transaction();

            // Replace the package file in the SCORM activity's filearea.
            $cm         = get_coursemodule_from_id('scorm', $row->cmid, $row->courseid, false, MUST_EXIST);
            $modcontext = \context_module::instance($cm->id);
            $fs         = get_file_storage();

            $fs->delete_area_files($modcontext->id, 'mod_scorm', 'package', 0);
            $fs->create_file_from_pathname([
                'contextid' => $modcontext->id,
                'component' => 'mod_scorm',
                'filearea'  => 'package',
                'itemid'    => 0,
                'filepath'  => '/',
                'filename'  => $contentid . '.zip',
            ], $tmpzip);

            // Re-parse the SCORM manifest without wiping user attempts.
            $scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
            scorm_parse($scorm, true);

            $DB->update_record('local_tituscontentlibrary_added', (object)[
                'id'           => $addedrowid,
                'status'       => content_status::COMPLETED,
                'errormessage' => null,
                'timemodified' => time(),
            ]);

            $transaction->allow_commit();
            rebuild_course_cache($row->courseid, true);
            mtrace('[titus] resync_content_task completed: ' . $contentid . ' (course ' . $row->courseid . ')');

            // Fire the course_resynced event (MLFR-220).
            \local_tituscontentlibrary\event\course_resynced::create_event(
                (int)$row->courseid,
                $contentid,
                (int)$row->addedby,
                $addedrowid
            )->trigger();

        } catch (\Throwable $e) {
            if ($transaction !== null) {
                try {
                    $DB->rollback_delegated_transaction($transaction, $e);
                } catch (\Throwable $re) {
                    debugging('[titus] resync rollback failed: ' . $re->getMessage(), DEBUG_DEVELOPER);
                }
            }
            try {
                $DB->update_record('local_tituscontentlibrary_added', (object)[
                    'id'           => $addedrowid,
                    'status'       => content_status::FAILED,
                    'errormessage' => $e->getMessage(),
                    'timemodified' => time(),
                ]);
            } catch (\Throwable $ue) {
                debugging('[titus] resync failed to update error status: ' . $ue->getMessage(), DEBUG_DEVELOPER);
            }
            throw $e;
        } finally {
            if ($tmpzip !== null && file_exists($tmpzip)) {
                @unlink($tmpzip);
            }
        }
    }
}
