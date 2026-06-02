<?php
/**
 * Smoke test 04: get_add_status external function.
 *
 * Depends on smoke_test_03 having run (reads smoke_last_contentid from plugin config).
 *
 * Tests:
 *  - Known completed contentid → status='completed', course_url populated.
 *  - Unknown contentid → status='notqueued'.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_04_status.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');

echo "=== Smoke Test 04: Status WS ===\n\n";

titus_smoke::become_admin();

$contentid = titus_smoke::get_state('last_contentid');
if (empty($contentid)) {
    echo "  [ERROR] smoke_last_contentid not set — run smoke_test_03 first.\n";
    exit(1);
}

// Test 1: Status for the completed item.
$result = \local_tituscontentlibrary\external\get_add_status::execute($contentid);
titus_smoke::assert_equals(
    \local_tituscontentlibrary\local\content_status::COMPLETED,
    $result['status'],
    "get_add_status({$contentid}) → status=completed"
);
titus_smoke::assert_not_empty($result['course_url'], 'course_url is populated when completed');
titus_smoke::assert_true(
    str_contains($result['course_url'] ?? '', '/course/view.php'),
    'course_url points to course view page'
);
titus_smoke::assert_true($result['errormessage'] === null, 'errormessage is null when completed');

// Test 2: Unknown contentid returns notqueued.
$unknown = \local_tituscontentlibrary\external\get_add_status::execute('titus-nonexistent-999');
titus_smoke::assert_equals('notqueued', $unknown['status'], 'Unknown contentid → status=notqueued');
titus_smoke::assert_true($unknown['course_url'] === null, 'course_url is null for notqueued');

exit(titus_smoke::summary_and_exit_code());
