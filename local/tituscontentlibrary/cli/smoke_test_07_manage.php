<?php
/**
 * Smoke test 07: Manage-page operations.
 *
 * Depends on smoke_test_03 having completed successfully.
 *
 * Tests:
 *  - resync_course::execute() throws moodle_exception for non-completed rows.
 *  - catalogue_manager::remove_added_row() deletes only the tracking row,
 *    leaving the Moodle course intact.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_07_manage.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');

echo "=== Smoke Test 07: Manage Operations ===\n\n";

titus_smoke::become_admin();

$contentid  = titus_smoke::get_state('last_contentid');
$addedrowid = (int)titus_smoke::get_state('last_addedrowid');

if (empty($contentid) || $addedrowid <= 0) {
    echo "  [ERROR] State from smoke_test_03 not found — run it first.\n";
    exit(1);
}

// Grab the courseid before we delete the row.
$row = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
if (!$row) {
    echo "  [ERROR] Row {$addedrowid} not found — has smoke_test_03 run?\n";
    exit(1);
}
$courseid = (int)$row->courseid;

// Test 1: resync rejects a PENDING row.
// Temporarily insert a fresh pending row for a different contentid.
$fakeid = $DB->insert_record('local_tituscontentlibrary_added', (object)[
    'contentid'    => 'titus-pending-smoke',
    'addedby'      => $USER->id,
    'status'       => \local_tituscontentlibrary\local\content_status::PENDING,
    'timecreated'  => time(),
    'timemodified' => time(),
]);

try {
    \local_tituscontentlibrary\external\resync_course::execute('titus-pending-smoke');
    titus_smoke::assert_true(false, 'resync_course on PENDING row should throw moodle_exception');
} catch (\moodle_exception $e) {
    titus_smoke::assert_contains('error:cannotresync_notcompleted', $e->errorcode,
        'resync_course throws error:cannotresync_notcompleted for PENDING row');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'Unexpected exception: ' . get_class($e) . ': ' . $e->getMessage());
} finally {
    $DB->delete_records('local_tituscontentlibrary_added', ['id' => $fakeid]);
}

// Test 2: remove_added_row() deletes the tracking row.
$mgr = new \local_tituscontentlibrary\local\catalogue_manager();
$mgr->remove_added_row($addedrowid);

$gone = $DB->get_record('local_tituscontentlibrary_added', ['id' => $addedrowid]);
titus_smoke::assert_true($gone === false, 'Tracking row deleted after remove_added_row()');

// Test 3: The Moodle course is NOT deleted.
$course = $DB->get_record('course', ['id' => $courseid]);
titus_smoke::assert_true($course !== false, "Moodle course (id={$courseid}) survives row removal");

// Test 4: get_add_status now returns notqueued (row is gone).
$status = \local_tituscontentlibrary\external\get_add_status::execute($contentid);
titus_smoke::assert_equals('notqueued', $status['status'],
    'get_add_status returns notqueued after row deletion');

echo "\nNote: Course id={$courseid} left in DB (smoke test cleanup not needed).\n";

exit(titus_smoke::summary_and_exit_code());
