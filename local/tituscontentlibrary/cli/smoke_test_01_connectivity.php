<?php
/**
 * Smoke test 01: HTTP connectivity to the Titus Catalogue simulator.
 *
 * Uses the Docker-internal hostname (http://webserver) so that tests work
 * without an active ngrok tunnel.
 *
 * Tests:
 *  - Valid licence key → 200 with results array.
 *  - Wrong licence key → 401.
 *  - Missing licence header → 401.
 *  - smoke_api_client::get_catalogue() returns content_dto objects.
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_01_connectivity.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "=== Smoke Test 01: Connectivity ===\n\n";

titus_smoke::become_admin();

$validkey     = \local_tituscontentlibrary\config::get_licence_key();
$internalbase = smoke_titus_api_client::get_base();
$catalogueurl = $internalbase . '/catalogue.php';

// Helper: raw cURL call against the internal Docker hostname.
$do_request = function(string $url, array $headers): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'error' => $err];
};

// Test 1: Valid key returns HTTP 200 and the brief-compliant response shape.
$r = $do_request($catalogueurl, ['X-Licence-Key: ' . $validkey]);
titus_smoke::assert_equals(200, $r['code'], 'Valid key → HTTP 200');
if ($r['code'] === 200) {
    $payload = json_decode($r['body'], true);
    // Brief: response is {tier, content:[], total, page, per_page}
    titus_smoke::assert_true(isset($payload['content']) && count($payload['content']) > 0, 'Response contains non-empty content array');
    titus_smoke::assert_true(isset($payload['tier']) && $payload['tier'] === 'full', 'Response echoes tier=full for full-tier key');
    titus_smoke::assert_true(isset($payload['total']) && $payload['total'] > 0, 'Response contains total > 0');
}

// Test 2: Invalid key returns HTTP 401.
$r = $do_request($catalogueurl, ['X-Licence-Key: INVALID-KEY-XXXX']);
titus_smoke::assert_equals(401, $r['code'], 'Invalid key → HTTP 401');

// Test 3: Missing header returns HTTP 401.
$r = $do_request($catalogueurl, []);
titus_smoke::assert_equals(401, $r['code'], 'Missing X-Licence-Key header → HTTP 401');

// Test 4: smoke_api_client::get_catalogue() returns content_dto objects.
try {
    $client = new smoke_titus_api_client();
    $items  = $client->get_catalogue();
    titus_smoke::assert_true(count($items) > 0, 'smoke_api_client get_catalogue() returns items');
    titus_smoke::assert_true(
        $items[0] instanceof \local_tituscontentlibrary\api\dto\content_dto,
        'Items are content_dto instances'
    );
    titus_smoke::assert_not_empty($items[0]->id, 'content_dto has id');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'smoke_api_client::get_catalogue() threw: ' . $e->getMessage());
}

// Test 5: download.php returns a signed URL containing the serve.php path.
try {
    $signed = $client->get_signed_download_url('titus-001');
    titus_smoke::assert_contains('serve.php', $signed->url, 'Signed URL contains serve.php');
    titus_smoke::assert_contains($CFG->wwwroot, $signed->url, 'Signed URL uses configured wwwroot');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'get_signed_download_url threw: ' . $e->getMessage());
}

exit(titus_smoke::summary_and_exit_code());
