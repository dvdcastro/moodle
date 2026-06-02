<?php
/**
 * Smoke test 05: Re-sync pipeline.
 *
 * Depends on smoke_test_03 having completed successfully.
 *
 * Tests:
 *  - resync_course::execute() resets the row to PENDING and queues resync_content_task.
 *  - Draining resync_content_task transitions row back to COMPLETED.
 *  - The SCORM package file was replaced (different file id).
 *  - scorm_scoes still exist after re-sync.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_05_resync_pipeline.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "=== Smoke Test 05: Re-sync Pipeline ===\n\n";

titus_smoke::become_admin();

$contentid  = titus_smoke::get_state('last_contentid');
$addedrowid = (int)titus_smoke::get_state('last_addedrowid');

if (empty($contentid) || $addedrowid <= 0) {
    echo "  [ERROR] State from smoke_test_03 not found — run it first.\n";
    exit(1);
}

// Capture the current SCORM package file id before re-sync.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid], '*', MUST_EXIST);
$cm  = get_coursemodule_from_id('scorm', $row->cmid, $row->courseid, false, MUST_EXIST);
$modcontext = \context_module::instance($cm->id);
$fs         = get_file_storage();

$oldfiles  = $fs->get_area_files($modcontext->id, 'mod_scorm', 'package', 0, 'id DESC', false);
$oldfile   = reset($oldfiles);
$oldfileid = $oldfile ? $oldfile->get_id() : null;

titus_smoke::assert_not_empty($oldfileid, 'SCORM package file exists before re-sync');

// Small sleep so timemodified will differ.
sleep(1);

// Queue the re-sync via the external function.
echo "  Queuing resync_course::execute({$contentid})...\n";
try {
    $result = \local_tituscontentlibrary\external\resync_course::execute($contentid);
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'resync_course::execute() threw: ' . $e->getMessage());
    exit(titus_smoke::summary_and_exit_code());
}

titus_smoke::assert_true($result['queued'], 'resync_course returned queued=true');

// Row should now be PENDING again.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
titus_smoke::assert_equals(
    \local_tituscontentlibrary\local\content_status::PENDING,
    $row->status,
    'Row reset to pending after resync_course'
);

// Inject internal-network client so the task can reach the simulator.
\local_tituscontentlibrary\api\client_factory::set_test_client(new smoke_titus_api_client());

// Drain the resync task.
echo "  Draining resync_content_task...\n";
try {
    $executed = titus_smoke::drain_adhoc('\local_tituscontentlibrary\task\resync_content_task');
    titus_smoke::assert_equals(1, $executed, 'Exactly 1 resync_content_task executed');
} catch (\RuntimeException $e) {
    titus_smoke::assert_true(false, 'resync_content_task threw: ' . $e->getMessage());
    \local_tituscontentlibrary\api\client_factory::reset();
    exit(titus_smoke::summary_and_exit_code());
}

\local_tituscontentlibrary\api\client_factory::reset();

// Row is COMPLETED again.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
titus_smoke::assert_equals(
    \local_tituscontentlibrary\local\content_status::COMPLETED,
    $row->status,
    'Row back to completed after re-sync task'
);

// SCORM package file was replaced (new file id).
$newfiles  = $fs->get_area_files($modcontext->id, 'mod_scorm', 'package', 0, 'id DESC', false);
$newfile   = reset($newfiles);
$newfileid = $newfile ? $newfile->get_id() : null;
titus_smoke::assert_not_empty($newfileid, 'SCORM package file still exists after re-sync');
titus_smoke::assert_true($newfileid !== $oldfileid, 'SCORM package file was replaced (different file id)');

// scorm_scoes still present.
$scorm    = $DB->get_record('scorm', ['course' => $row->courseid]);
$scocount = $DB->count_records('scorm_scoes', ['scorm' => $scorm->id]);
titus_smoke::assert_greater_than(0, $scocount, "scorm_scoes still present after re-sync ({$scocount} rows)");

exit(titus_smoke::summary_and_exit_code());
