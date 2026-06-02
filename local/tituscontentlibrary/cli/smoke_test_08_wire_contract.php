<?php
/**
 * Smoke test 08: Wire-format contract validation.
 *
 * Verifies that the live HTTP responses from the Titus Catalogue simulator
 * match the canonical wire format defined in the Titus dev brief, and that
 * the smoke API client's DTOs expose the post-Wave-A field names.
 *
 * Tests:
 *  T8-01  GET /catalogue returns {tier, content[], total, page, per_page}
 *  T8-02  GET /catalogue?per_page=3&page=1 honours pagination params
 *  T8-03  GET /catalogue?search=Leadership narrows the result set
 *  T8-04  POST /download returns {url, filename, expires_in}
 *  T8-05  smoke_api_client::get_catalogue() returns content_dto with new fields
 *  T8-06  smoke_api_client::get_signed_download_url() returns signed_url_dto with filename
 *  T8-07  smoke_api_client::health() returns true
 *  T8-08  Invalid licence key returns HTTP 401
 *
 * Usage: php local/tituscontentlibrary/cli/smoke_test_08_wire_contract.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/smoke_common.php');
require_once(__DIR__ . '/smoke_api_client.php');

echo "\n=== Stage 8: Wire contract validation ===\n";

titus_smoke::become_admin();

$validkey     = \local_tituscontentlibrary\config::get_licence_key();
$internalbase = smoke_titus_api_client::get_base();
$catalogueurl = $internalbase . '/catalogue.php';
$downloadurl  = $internalbase . '/download.php';

// Helper: raw cURL GET (same pattern as smoke_test_01).
$do_get = function(string $url, array $headers): array {
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

// Helper: raw cURL POST with JSON body.
$do_post = function(string $url, array $headers, string $body): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => array_merge($headers, ['Content-Type: application/json']),
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['code' => $code, 'body' => $resp, 'error' => $err];
};

$validheaders = ['X-Licence-Key: ' . $validkey, 'X-Site-URL: ' . $CFG->wwwroot];

// -----------------------------------------------------------------------------
// T8-01: GET /catalogue returns {tier, content[], total, page, per_page}.
// -----------------------------------------------------------------------------
$r = $do_get($catalogueurl, $validheaders);
titus_smoke::assert_equals(200, $r['code'], 'T8-01 GET /catalogue → HTTP 200');
$payload = json_decode($r['body'] ?? '', true);
titus_smoke::assert_true(is_array($payload), 'T8-01 Response body is JSON object');
if (is_array($payload)) {
    titus_smoke::assert_true(
        array_key_exists('tier', $payload) && is_string($payload['tier']) && $payload['tier'] !== '',
        'T8-01 Response has non-empty string "tier"'
    );
    titus_smoke::assert_true(
        array_key_exists('content', $payload) && is_array($payload['content']) && count($payload['content']) > 0,
        'T8-01 Response has non-empty "content" array (NOT "results")'
    );
    titus_smoke::assert_false(
        array_key_exists('results', $payload),
        'T8-01 Response does NOT use legacy "results" key'
    );
    titus_smoke::assert_true(
        array_key_exists('total', $payload) && is_int($payload['total']) && $payload['total'] > 0,
        'T8-01 Response has int "total" > 0'
    );
    titus_smoke::assert_true(array_key_exists('page', $payload),     'T8-01 Response has "page"');
    titus_smoke::assert_true(array_key_exists('per_page', $payload), 'T8-01 Response has "per_page"');
}

// -----------------------------------------------------------------------------
// T8-02: pagination params (per_page=3, page=1) honoured.
// -----------------------------------------------------------------------------
$r = $do_get($catalogueurl . '?per_page=3&page=1', $validheaders);
titus_smoke::assert_equals(200, $r['code'], 'T8-02 GET /catalogue?per_page=3&page=1 → HTTP 200');
$payload = json_decode($r['body'] ?? '', true);
if (is_array($payload)) {
    titus_smoke::assert_equals(3, (int)($payload['per_page'] ?? 0), 'T8-02 Response echoes per_page=3');
    $count = isset($payload['content']) && is_array($payload['content']) ? count($payload['content']) : -1;
    titus_smoke::assert_true(
        $count >= 0 && $count <= 3,
        "T8-02 content array honours per_page cap (count={$count}, cap=3)"
    );
}

// -----------------------------------------------------------------------------
// T8-03: search filter narrows the result set.
// Seed data includes "Leadership Foundations", "Advanced Leadership",
// "Leadership in Crisis" — 3 of 12 items.
// -----------------------------------------------------------------------------
$r = $do_get($catalogueurl . '?search=Leadership', $validheaders);
titus_smoke::assert_equals(200, $r['code'], 'T8-03 GET /catalogue?search=Leadership → HTTP 200');
$payload = json_decode($r['body'] ?? '', true);
if (is_array($payload) && isset($payload['content']) && is_array($payload['content'])) {
    $items = $payload['content'];
    $total = (int)($payload['total'] ?? 0);
    titus_smoke::assert_true(
        count($items) > 0 && count($items) < 12,
        'T8-03 search=Leadership narrows the result set (got ' . count($items) . ' of 12)'
    );
    $all_match = true;
    foreach ($items as $item) {
        $hay = strtolower(($item['title'] ?? '') . ' ' . ($item['description'] ?? '') . ' ' . ($item['short_description'] ?? ''));
        if (!str_contains($hay, 'leadership')) {
            $all_match = false;
            break;
        }
    }
    titus_smoke::assert_true($all_match, 'T8-03 every returned item contains "leadership" in title/description');
}

// -----------------------------------------------------------------------------
// T8-04: POST /download returns {url, filename, expires_in}.
// -----------------------------------------------------------------------------
$r = $do_post($downloadurl, $validheaders, json_encode(['content_id' => 'titus-001']));
titus_smoke::assert_equals(200, $r['code'], 'T8-04 POST /download → HTTP 200');
$payload = json_decode($r['body'] ?? '', true);
titus_smoke::assert_true(is_array($payload), 'T8-04 Response body is JSON object');
if (is_array($payload)) {
    titus_smoke::assert_true(
        array_key_exists('url', $payload) && is_string($payload['url']) && $payload['url'] !== '',
        'T8-04 Response has non-empty "url" (NOT "download_url")'
    );
    titus_smoke::assert_false(
        array_key_exists('download_url', $payload),
        'T8-04 Response does NOT use legacy "download_url" key'
    );
    if (isset($payload['url']) && is_string($payload['url'])) {
        titus_smoke::assert_contains('serve.php', $payload['url'], 'T8-04 url contains serve.php');
    }
    titus_smoke::assert_true(
        array_key_exists('filename', $payload) && is_string($payload['filename']) && $payload['filename'] !== '',
        'T8-04 Response has non-empty "filename"'
    );
    titus_smoke::assert_equals(300, (int)($payload['expires_in'] ?? 0), 'T8-04 expires_in === 300');
}

// -----------------------------------------------------------------------------
// T8-05: smoke_api_client returns content_dto with the new field set.
// -----------------------------------------------------------------------------
try {
    $client = new smoke_titus_api_client();
    $items  = $client->get_catalogue();
    titus_smoke::assert_true(count($items) > 0, 'T8-05 get_catalogue() returns items');
    $first = $items[0] ?? null;
    titus_smoke::assert_true(
        $first instanceof \local_tituscontentlibrary\api\dto\content_dto,
        'T8-05 first item is content_dto'
    );
    if ($first instanceof \local_tituscontentlibrary\api\dto\content_dto) {
        titus_smoke::assert_not_empty($first->id,    'T8-05 content_dto has non-empty id');
        titus_smoke::assert_not_empty($first->title, 'T8-05 content_dto has non-empty title');
        titus_smoke::assert_true(is_string($first->category),    'T8-05 content_dto->category is string');
        titus_smoke::assert_true(is_string($first->subcategory), 'T8-05 content_dto->subcategory is string');
        titus_smoke::assert_true(is_string($first->version),     'T8-05 content_dto->version is string');
        titus_smoke::assert_true(
            $first->updated_at === null || is_int($first->updated_at),
            'T8-05 content_dto->updated_at is null or int (renamed from published_at)'
        );
    }
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'T8-05 get_catalogue() threw: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// T8-06: signed_url_dto has url + filename + expires_in=300.
// -----------------------------------------------------------------------------
try {
    $client = $client ?? new smoke_titus_api_client();
    $signed = $client->get_signed_download_url('titus-001');
    titus_smoke::assert_true(
        $signed instanceof \local_tituscontentlibrary\api\dto\signed_url_dto,
        'T8-06 returns signed_url_dto'
    );
    titus_smoke::assert_not_empty($signed->url,      'T8-06 signed_url_dto->url is non-empty');
    titus_smoke::assert_not_empty($signed->filename, 'T8-06 signed_url_dto->filename is non-empty (Wave A field)');
    titus_smoke::assert_equals(300, $signed->expires_in, 'T8-06 signed_url_dto->expires_in === 300');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'T8-06 get_signed_download_url() threw: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// T8-07: health() returns true against the live simulator.
// -----------------------------------------------------------------------------
try {
    $client = $client ?? new smoke_titus_api_client();
    $alive  = $client->health();
    titus_smoke::assert_true($alive === true, 'T8-07 smoke_api_client::health() returns true');
} catch (\Throwable $e) {
    titus_smoke::assert_true(false, 'T8-07 health() threw: ' . $e->getMessage());
}

// -----------------------------------------------------------------------------
// T8-08: invalid licence key → HTTP 401.
// -----------------------------------------------------------------------------
$r = $do_get($catalogueurl, ['X-Licence-Key: INVALID-XXXX', 'X-Site-URL: ' . $CFG->wwwroot]);
titus_smoke::assert_equals(401, $r['code'], 'T8-08 Invalid licence key → HTTP 401');

exit(titus_smoke::summary_and_exit_code());
