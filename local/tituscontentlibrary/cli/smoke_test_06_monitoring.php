<?php
/**
 * Smoke test 06: Monitoring — failure streak and recovery.
 *
 * Uses smoke_failing_api_client to simulate 3 consecutive catalogue failures
 * (no real network needed), then smoke_titus_api_client for recovery.
 *
 * Tests:
 *  - 3 consecutive get_catalogue() failures build failure_streak >= 3.
 *  - A successful fetch resets failure_streak to 0 and sets last_success.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_06_monitoring.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "=== Smoke Test 06: Monitoring ===\n\n";

titus_smoke::become_admin();

// Reset monitoring counters to a clean state.
set_config(\local_tituscontentlibrary\local\monitoring::CFG_FAILURE_STREAK,  0, 'local_tituscontentlibrary');
set_config(\local_tituscontentlibrary\local\monitoring::CFG_LAST_SUCCESS,    0, 'local_tituscontentlibrary');
set_config(\local_tituscontentlibrary\local\monitoring::CFG_TOTAL_REFRESHES, 0, 'local_tituscontentlibrary');

// Inject the failing client to simulate 3 API outages.
\local_tituscontentlibrary\api\client_factory::set_test_client(new smoke_failing_api_client());

echo "  Fetching 3× with failing client to build failure streak...\n";
for ($i = 1; $i <= 3; $i++) {
    $mgr = new \local_tituscontentlibrary\local\catalogue_manager();
    $mgr->invalidate_cache();
    $items = $mgr->get_catalogue(); // Should fail silently (degraded mode).
    titus_smoke::assert_true(is_array($items), "Degraded-mode fetch #{$i} returns array (empty or stale)");
    titus_smoke::assert_equals(0, count($items), "Degraded-mode fetch #{$i} returns empty array (no stale data yet)");
}

$stats = \local_tituscontentlibrary\local\monitoring::get_stats();
titus_smoke::assert_greater_than(2, $stats['failure_streak'], 'Failure streak >= 3 after 3 failed fetches');
titus_smoke::assert_equals(0, $stats['last_success'], 'last_success remains 0 (no success yet)');

// Switch to the working internal client and verify recovery.
\local_tituscontentlibrary\api\client_factory::set_test_client(new smoke_titus_api_client());

$mgr = new \local_tituscontentlibrary\local\catalogue_manager();
$mgr->invalidate_cache();
$items = $mgr->get_catalogue();

$stats = \local_tituscontentlibrary\local\monitoring::get_stats();
titus_smoke::assert_equals(0, $stats['failure_streak'], 'Failure streak reset to 0 after successful fetch');
titus_smoke::assert_greater_than(0, $stats['last_success'], 'last_success is set after successful fetch');
titus_smoke::assert_true(count($items) > 0, 'Recovery fetch returns catalogue items');

\local_tituscontentlibrary\api\client_factory::reset();

exit(titus_smoke::summary_and_exit_code());
