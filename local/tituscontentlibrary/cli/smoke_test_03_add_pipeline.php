<?php
/**
 * Smoke test 03: Full add-content pipeline.
 *
 * Tests:
 *  - add_course::execute() inserts a PENDING row and queues an adhoc task.
 *  - Draining add_content_task transitions the row to COMPLETED.
 *  - A Moodle course is created.
 *  - A SCORM course module is created and parsed (scorm_scoes rows exist).
 *
 * Persists smoke_last_addedrowid and smoke_last_contentid for later scripts.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_03_add_pipeline.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "=== Smoke Test 03: Add Pipeline ===\n\n";

titus_smoke::become_admin();

// Pick titus-001 (full-tier item, always available).
$contentid = 'titus-001';

// 1. Queue the add via the external function.
echo "  Queuing {$contentid} via add_course::execute()...\n";
try {
    $result = \local_tituscontentlibrary\external\add_course::execute($contentid);
} catch (\Throwable $e) {
    echo "  [FAIL] add_course::execute() threw: " . $e->getMessage() . "\n";
    exit(1);
}

titus_smoke::assert_true($result['queued'], 'add_course returned queued=true');
titus_smoke::assert_false($result['alreadyqueued'], 'add_course returned alreadyqueued=false');
titus_smoke::assert_equals(\local_tituscontentlibrary\local\content_status::PENDING, $result['status'], 'Initial status is pending');
$addedrowid = (int)$result['addedrowid'];
titus_smoke::assert_greater_than(0, $addedrowid, 'addedrowid > 0');

// 2. Row is in DB with status=pending.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
titus_smoke::assert_true($row !== false, 'Row exists in DB');
titus_smoke::assert_equals(\local_tituscontentlibrary\local\content_status::PENDING, $row->status, 'Row status = pending');

// 3. Inject the internal-network client so the task can reach the simulator.
\local_tituscontentlibrary\api\client_factory::set_test_client(new smoke_titus_api_client());

// 4. Drain the add_content_task.
echo "  Draining add_content_task...\n";
try {
    $executed = titus_smoke::drain_adhoc('\local_tituscontentlibrary\task\add_content_task');
    titus_smoke::assert_equals(1, $executed, 'Exactly 1 add_content_task executed');
} catch (\RuntimeException $e) {
    titus_smoke::assert_true(false, 'add_content_task threw: ' . $e->getMessage());
    \local_tituscontentlibrary\api\client_factory::reset();
    exit(titus_smoke::summary_and_exit_code());
}

\local_tituscontentlibrary\api\client_factory::reset();

// 5. Row is now COMPLETED with courseid and cmid set.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
titus_smoke::assert_equals(\local_tituscontentlibrary\local\content_status::COMPLETED, $row->status, 'Row status = completed after task');
titus_smoke::assert_not_empty($row->courseid, 'Row has courseid');
titus_smoke::assert_not_empty($row->cmid, 'Row has cmid');

// 6. Moodle course exists.
$course = $DB->get_record('course', ['id' => $row->courseid]);
titus_smoke::assert_true($course !== false, 'Moodle course record exists');
titus_smoke::assert_not_empty($course->fullname, 'Course has a fullname');

// 7. SCORM cm exists and is visible.
$cm = get_coursemodule_from_id('scorm', $row->cmid);
titus_smoke::assert_true($cm !== false, 'SCORM course module exists');

// 8. SCORM package has been parsed (scorm_scoes rows exist).
if ($cm) {
    $scorm = $DB->get_record('scorm', ['course' => $row->courseid]);
    titus_smoke::assert_true($scorm !== false, 'scorm record exists');
    if ($scorm) {
        $scocount = $DB->count_records('scorm_scoes', ['scorm' => $scorm->id]);
        titus_smoke::assert_greater_than(0, $scocount, "scorm_scoes parsed ({$scocount} rows)");
    }
}

// 9. Persist state for downstream scripts.
titus_smoke::set_state('last_addedrowid', (string)$addedrowid);
titus_smoke::set_state('last_contentid', $contentid);
echo "  State saved: rowid={$addedrowid}, contentid={$contentid}\n";

exit(titus_smoke::summary_and_exit_code());
