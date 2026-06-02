<?php
/**
 * Smoke test setup: configure the plugin to point at the local simulator.
 *
 * Must be run before any other smoke test script.
 * Exits with code 0 on success, 1 on failure.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_setup.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');

echo "=== Titus Smoke Test Setup ===\n\n";

titus_smoke::become_admin();

// 1. Drain any leftover plugin adhoc tasks from previous smoke runs.
$titustask_classes = [
    'local_tituscontentlibrary\task\add_content_task',
    'local_tituscontentlibrary\task\resync_content_task',
];
$cleaned = 0;
for ($i = 0; $i < 20; $i++) {
    $task = \core\task\manager::get_next_adhoc_task(time());
    if ($task === null) {
        break;
    }
    $taskclass = ltrim(get_class($task), '\\');
    if (in_array($taskclass, $titustask_classes)) {
        \core\task\manager::adhoc_task_failed($task);
        $cleaned++;
    } else {
        // Non-plugin task — put it back and stop.
        \core\task\manager::adhoc_task_failed($task);
        break;
    }
}
if ($cleaned > 0) {
    echo "  [INFO] Cleared {$cleaned} leftover plugin adhoc task(s).\n";
}

// Now verify the queue is clear of plugin tasks.
titus_smoke::assert_adhoc_queue_empty('Adhoc queue is empty before setup');

// 2. Verify mod_scorm is enabled.
$pluginman = \core_plugin_manager::instance();
$scorminfo = $pluginman->get_plugin_info('mod_scorm');
titus_smoke::assert_true(
    $scorminfo !== null && $scorminfo->is_enabled() !== false,
    'mod_scorm is enabled'
);

// 3. Verify local_titusclsim is installed.
$siminfo = $pluginman->get_plugin_info('local_titusclsim');
titus_smoke::assert_true(
    $siminfo !== null && $siminfo->is_enabled() !== false,
    'local_titusclsim is installed and enabled'
);

// 4. Configure apibaseurl to point at the simulator.
$simurl = rtrim($CFG->wwwroot, '/') . '/local/titusclsim/api';
set_config('apibaseurl', $simurl, 'local_tituscontentlibrary');
titus_smoke::assert_equals(
    $simurl,
    \local_tituscontentlibrary\config::get_api_base_url(),
    "apibaseurl set to simulator ({$simurl})"
);

// 5. Set and encrypt a valid licence key.
// The simulator seeds TITUS-FULL-KEY-001 (tier=full) which returns all 12
// catalogue items. See local/titusclsim/db/install.php.
$rawkey = 'TITUS-FULL-KEY-001';
try {
    $encrypted = \core\encryption::encrypt($rawkey);
    set_config('licencekey', $encrypted, 'local_tituscontentlibrary');
    $roundtrip = \local_tituscontentlibrary\config::get_licence_key();
    titus_smoke::assert_equals($rawkey, $roundtrip, 'Licence key round-trips through encryption');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'Licence key encryption: ' . $e->getMessage());
}

// 6. Reset monitoring counters.
set_config(\local_tituscontentlibrary\local\monitoring::CFG_FAILURE_STREAK,  0, 'local_tituscontentlibrary');
set_config(\local_tituscontentlibrary\local\monitoring::CFG_LAST_SUCCESS,    0, 'local_tituscontentlibrary');
set_config(\local_tituscontentlibrary\local\monitoring::CFG_TOTAL_REFRESHES, 0, 'local_tituscontentlibrary');
titus_smoke::assert_true(true, 'Monitoring counters reset');

// 7. Purge string caches so newly-added lang strings are picked up.
get_string_manager()->reset_caches();
titus_smoke::assert_true(true, 'String caches purged');

// 8. Invalidate catalogue MUC cache.
$mgr = new \local_tituscontentlibrary\local\catalogue_manager();
$mgr->invalidate_cache();
titus_smoke::assert_true(true, 'Catalogue MUC cache invalidated');

// 9. Seed a SCORM package into the simulator for titus-001.
// The serve.php endpoint needs a stored file in local_titusclsim/packages/<itemid>/<filename>.
// We reuse Moodle's own singlescobasic.zip from mod/scorm tests (contains imsmanifest.xml).
$scorm_fixture = $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip';
$simitem = $DB->get_record('local_titusclsim_catalogue', ['content_id' => 'titus-001']);
if ($simitem && file_exists($scorm_fixture)) {
    $fs   = get_file_storage();
    $ctx  = context_system::instance();
    $fname = 'titus-001.zip';
    // Remove any existing package file for this item.
    $fs->delete_area_files($ctx->id, 'local_titusclsim', 'packages', $simitem->id);
    // Store the fixture as the simulator package.
    $filerecord = [
        'contextid' => $ctx->id,
        'component' => 'local_titusclsim',
        'filearea'  => 'packages',
        'itemid'    => $simitem->id,
        'filepath'  => '/',
        'filename'  => $fname,
    ];
    $storedfile = $fs->create_file_from_pathname($filerecord, $scorm_fixture);
    // Mark as available.
    $DB->update_record('local_titusclsim_catalogue', (object)[
        'id'          => $simitem->id,
        'has_package' => 1,
        'filename'    => $fname,
    ]);
    titus_smoke::assert_true($storedfile !== false, 'SCORM package seeded for titus-001');
} else {
    titus_smoke::assert_true(false, 'Cannot seed SCORM package: fixture or DB record missing');
}

// 10. Clean up any leftover smoke state from previous runs.
$DB->delete_records_select(
    'local_tituscontentlibrary_added',
    "contentid LIKE 'titus-%'"
);
titus_smoke::assert_true(true, 'Cleaned up previous smoke test rows');

// 11. Persist simulator URL for other scripts.
titus_smoke::set_state('simurl', $simurl);

echo "\nSetup complete.\n";
exit(titus_smoke::summary_and_exit_code());
