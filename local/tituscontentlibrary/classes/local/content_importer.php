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

namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\config;

/**
 * Imports a Titus content item as a Moodle course + SCORM activity.
 *
 * This class owns the download → validate → course/SCORM creation pipeline and
 * its delegated transaction. It does NOT touch the _added row status or fire
 * events — those are the caller's responsibility (add_content_task for async,
 * add_course WS for sync).
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content_importer {

    /**
     * Download a Titus SCORM package and create the Moodle course + SCORM activity.
     *
     * Performs its own delegated transaction (start → commit, or rollback on failure).
     * Does NOT write to local_tituscontentlibrary_added or fire events.
     *
     * @param string $contentid Titus content identifier.
     * @param int    $userid    ID of the user performing the import (used for draft file context).
     * @return \stdClass Object with properties: courseid, cmid, scormid.
     * @throws \moodle_exception|\local_tituscontentlibrary\api\exception\titus_exception on failure.
     */
    public function import(string $contentid, int $userid): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');
        require_once($CFG->dirroot . '/mod/scorm/locallib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $tmpzip      = null;
        $transaction = null;

        try {
            // Find the content_dto in the catalogue.
            $manager = new catalogue_manager();
            $items   = $manager->get_catalogue(false);
            $dto     = null;
            foreach ($items as $item) {
                if ($item->id === $contentid) {
                    $dto = $item;
                    break;
                }
            }
            if ($dto === null) {
                throw new \moodle_exception('error:contentidnotincatalogue', 'local_tituscontentlibrary', '', $contentid);
            }

            // Get signed URL and download ZIP.
            $client = client_factory::get_client();
            $signed = $client->get_signed_download_url($contentid);
            $tmpzip = make_request_directory() . '/' . $contentid . '.zip';
            $client->download_to($signed->url, $tmpzip);

            // Validate the SCORM ZIP before touching the DB.
            scorm_zip_validator::validate($tmpzip);

            // Begin delegated transaction.
            $transaction = $DB->start_delegated_transaction();

            // Create the course.
            $shortname = 'ML-' . $contentid . '-' . time();
            $course = create_course((object)[
                'fullname'      => $dto->title,
                'shortname'     => $shortname,
                'category'      => config::get_default_category_id(),
                'summary'       => $dto->description,
                'summaryformat' => FORMAT_HTML,
                'format'        => 'topics',
                'visible'       => 1,
                'numsections'   => 1,
            ]);

            // Prepare draft file under the user's context.
            $usercontext = \context_user::instance($userid);
            $draftitemid = file_get_unused_draft_itemid();
            $fs          = get_file_storage();
            $fs->create_file_from_pathname([
                'contextid' => $usercontext->id,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $draftitemid,
                'filepath'  => '/',
                'filename'  => $contentid . '.zip',
            ], $tmpzip);

            // Add course module record (instance=0), then create SCORM instance.
            $moduleid = $DB->get_field('modules', 'id', ['name' => 'scorm'], MUST_EXIST);
            $cmid = add_course_module((object)[
                'course'                    => $course->id,
                'module'                    => $moduleid,
                'instance'                  => 0,
                'section'                   => 1,
                'idnumber'                  => '',
                'score'                     => 0,
                'indent'                    => 0,
                'visible'                   => 1,
                'visibleoncoursepage'       => 1,
                'groupmode'                 => 0,
                'groupingid'                => 0,
                'completion'                => COMPLETION_TRACKING_AUTOMATIC,
                'completiongradeitemnumber' => null,
                'completionview'            => 0,
                'completionexpected'        => 0,
                'showdescription'           => 0,
                'availability'              => null,
            ]);

            $scormid = scorm_add_instance((object)[
                'course'                   => $course->id,
                'coursemodule'             => $cmid,
                'cmidnumber'               => '',
                'name'                     => $dto->title,
                'intro'                    => $dto->description,
                'introformat'              => FORMAT_HTML,
                'packagefile'              => $draftitemid,
                'scormtype'                => SCORM_TYPE_LOCAL,
                'popup'                    => 0,
                'width'                    => 100,
                'height'                   => 500,
                'skipview'                 => 0,
                'hidebrowse'               => 0,
                'displayattemptstatus'     => 1,
                'displaycoursestructure'   => 0,
                'updatefreq'               => SCORM_UPDATE_NEVER,
                'maxdattempt'              => 0,
                'maxgrade'                 => 100,
                'grademethod'              => GRADEHIGHEST,
                'whatgrade'                => 0,
                'nav'                      => SCORM_NAV_DISABLED,
                'navpositionleft'          => -100,
                'navpositiontop'           => -100,
                'auto'                     => 0,
                'timeopen'                 => 0,
                'timeclose'                => 0,
                'timemodified'             => time(),
            ], null);

            // Wire the SCORM instance id back into the course_modules row.
            $DB->set_field('course_modules', 'instance', $scormid, ['id' => $cmid]);
            // Add the cm to section 1 so it appears in the course.
            course_add_cm_to_section($course->id, $cmid, 1);

            $transaction->allow_commit();
            rebuild_course_cache($course->id, true);

            return (object)['courseid' => (int)$course->id, 'cmid' => (int)$cmid, 'scormid' => (int)$scormid];

        } catch (\Throwable $e) {
            if ($transaction !== null) {
                try {
                    $DB->rollback_delegated_transaction($transaction, $e);
                } catch (\Throwable $re) {
                    debugging('[titus] content_importer rollback failed: ' . $re->getMessage(), DEBUG_DEVELOPER);
                }
            }
            throw $e;
        } finally {
            if ($tmpzip !== null && file_exists($tmpzip)) {
                @unlink($tmpzip);
            }
        }
    }
}
