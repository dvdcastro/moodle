<?php
/**
 * Titus Content Library — full smoke test suite runner.
 *
 * Runs setup + all 7 smoke test scripts in sequence as subprocesses.
 * Aborts after setup if it fails (remaining tests depend on it).
 * Prints a [PASS]/[FAIL] summary table at the end.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_all.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

$php    = PHP_BINARY;
$root   = __DIR__;
$start  = time();
$results = [];

$scripts = [
    'setup'        => 'smoke_setup.php',
    'test01'       => 'smoke_test_01_connectivity.php',
    'test02'       => 'smoke_test_02_catalogue.php',
    'test03'       => 'smoke_test_03_add_pipeline.php',
    'test04'       => 'smoke_test_04_status.php',
    'test05'       => 'smoke_test_05_resync_pipeline.php',
    'test06'       => 'smoke_test_06_monitoring.php',
    'test07'       => 'smoke_test_07_manage.php',
    'test08'       => 'smoke_test_08_wire_contract.php',
];

$labels = [
    'setup'  => 'Setup                 ',
    'test01' => 'Test 01 — Connectivity',
    'test02' => 'Test 02 — Catalogue   ',
    'test03' => 'Test 03 — Add pipeline',
    'test04' => 'Test 04 — Status WS   ',
    'test05' => 'Test 05 — Re-sync     ',
    'test06' => 'Test 06 — Monitoring  ',
    'test07' => 'Test 07 — Manage ops  ',
    'test08' => 'Test 08 — Wire contract',
];

echo str_repeat('=', 56) . "\n";
echo "  Titus Content Library Smoke Test Suite\n";
echo str_repeat('=', 56) . "\n\n";

foreach ($scripts as $key => $filename) {
    $path = escapeshellarg("{$root}/{$filename}");
    $cmd  = "{$php} {$path} 2>&1";

    echo str_repeat('-', 56) . "\n";
    passthru($cmd, $exitcode);
    $results[$key] = ($exitcode === 0);

    if ($key === 'setup' && !$results[$key]) {
        echo "\n[ABORT] Setup failed — remaining tests skipped.\n";
        echo str_repeat('=', 56) . "\n";
        print_summary($results, $labels, $start);
        exit(1);
    }
}

echo str_repeat('=', 56) . "\n";
print_summary($results, $labels, $start);
$allfailed = count(array_filter($results, fn($v) => !$v));
exit($allfailed > 0 ? 1 : 0);

// ---------------------------------------------------------------------------

function print_summary(array $results, array $labels, int $start): void {
    $elapsed = time() - $start;
    $passed  = count(array_filter($results));
    $total   = count($results);

    echo "\nSummary ({$elapsed}s):\n\n";
    foreach ($results as $key => $ok) {
        $badge = $ok ? '[PASS]' : '[FAIL]';
        echo "  {$badge}  {$labels[$key]}\n";
    }
    echo "\n  {$passed}/{$total} passed\n\n";
}
