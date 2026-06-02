<?php
/**
 * Smoke test 02: Catalogue manager — caching and category listing.
 *
 * Tests:
 *  - Cold fetch returns 12 items (full tier, all fixture items).
 *  - Second fetch hits MUC cache (does not increment total_refreshes).
 *  - Force-refresh bypasses cache.
 *  - invalidate_cache() then fetch increments total_refreshes.
 *  - get_categories() returns a non-empty sorted unique list.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_02_catalogue.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "=== Smoke Test 02: Catalogue ===\n\n";

titus_smoke::become_admin();

// Inject internal-network client so no ngrok needed.
\local_tituscontentlibrary\api\client_factory::set_test_client(new smoke_titus_api_client());

// Ensure cache is cold.
$mgr = new \local_tituscontentlibrary\local\catalogue_manager();
$mgr->invalidate_cache();

// Test 1: Cold fetch from the simulator.
$items = $mgr->get_catalogue();
titus_smoke::assert_greater_than(0, count($items), 'Cold catalogue fetch returns items');
titus_smoke::assert_true(count($items) >= 12, 'Full-tier key returns all 12 fixture items (got ' . count($items) . ')');

// Test 2: Each item is a content_dto with required fields.
$first = reset($items);
titus_smoke::assert_true($first instanceof \local_tituscontentlibrary\api\dto\content_dto, 'Items are content_dto instances');
titus_smoke::assert_not_empty($first->id, 'content_dto has id');
titus_smoke::assert_not_empty($first->title, 'content_dto has title');

// Test 3: Second fetch comes from MUC cache (no API call).
$before = \local_tituscontentlibrary\local\monitoring::get_stats();
$items2 = $mgr->get_catalogue();
$after  = \local_tituscontentlibrary\local\monitoring::get_stats();
titus_smoke::assert_equals(count($items), count($items2), 'Cached fetch returns same count');
titus_smoke::assert_equals(
    $before['total_refreshes'],
    $after['total_refreshes'],
    'Cached fetch does not increment total_refreshes'
);

// Test 4: Force-refresh bypasses cache.
$before2 = \local_tituscontentlibrary\local\monitoring::get_stats();
$items3  = $mgr->get_catalogue(true);
$after2  = \local_tituscontentlibrary\local\monitoring::get_stats();
titus_smoke::assert_equals(count($items), count($items3), 'Force-refresh returns same item count');
titus_smoke::assert_equals(
    $before2['total_refreshes'] + 1,
    $after2['total_refreshes'],
    'Force-refresh increments total_refreshes'
);

// Test 5: invalidate then fetch increments refreshes.
$mgr->invalidate_cache();
$before3 = \local_tituscontentlibrary\local\monitoring::get_stats();
$mgr->get_catalogue();
$after3  = \local_tituscontentlibrary\local\monitoring::get_stats();
titus_smoke::assert_equals(
    $before3['total_refreshes'] + 1,
    $after3['total_refreshes'],
    'Post-invalidate fetch increments total_refreshes'
);

// Test 6: get_categories() returns sorted, unique, non-empty list.
$categories = $mgr->get_categories();
titus_smoke::assert_true(count($categories) > 0, 'get_categories() returns at least one category');
$sorted = $categories;
usort($sorted, 'strcasecmp');
titus_smoke::assert_equals($sorted, $categories, 'Categories are sorted case-insensitively');
titus_smoke::assert_equals(count(array_unique($categories)), count($categories), 'Categories are unique');

\local_tituscontentlibrary\api\client_factory::reset();

exit(titus_smoke::summary_and_exit_code());
