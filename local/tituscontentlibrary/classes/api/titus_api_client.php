<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_tituscontentlibrary\api;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\config;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;
use local_tituscontentlibrary\api\exception\titus_api_exception;
use local_tituscontentlibrary\api\exception\titus_auth_exception;
use local_tituscontentlibrary\api\exception\titus_not_found_exception;
use local_tituscontentlibrary\api\exception\titus_rate_limit_exception;
use local_tituscontentlibrary\api\exception\titus_server_exception;
use local_tituscontentlibrary\api\exception\titus_network_exception;
use local_tituscontentlibrary\api\exception\titus_security_exception;

/**
 * HTTP client for the Titus Content Library API.
 *
 * Uses Moodle's {@see download_file_content()} so that proxy, SSL and curl
 * settings configured by the Moodle admin are automatically respected.
 *
 * Response handling notes for download_file_content($url, $headers, $postdata, $fullresponse=true):
 *   - Returns a stdClass with ->status (string HTTP code), ->results (body string),
 *     ->headers (array of raw header lines), ->error (string), ->response_code.
 *   - On cURL-level failure ->status is '0' or '-100' and ->results is false.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class titus_api_client implements titus_api_client_interface {

    private string $baseurl;
    private string $licencekey;

    public function __construct() {
        $this->baseurl    = config::get_api_base_url();
        $this->licencekey = config::get_licence_key();
    }

    // -------------------------------------------------------------------------
    // Public interface
    // -------------------------------------------------------------------------

    /**
     * Fetch the full content catalogue from the Titus API.
     *
     * @param array $filters Optional query-string filters.
     * @return content_dto[]
     */
    public function get_catalogue(array $filters = []): array {
        $url = $this->baseurl . '/catalogue.php';
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }

        $body = $this->do_get($url);
        $data = $this->decode_json($body, $url);

        // The simulator wraps items in {"results":[...]}; raw array also accepted.
        $items = isset($data['results']) && is_array($data['results'])
            ? $data['results']
            : $data;

        return array_map([content_dto::class, 'from_array'], $items);
    }

    /**
     * Request a signed download URL for a given content item.
     *
     * @param string $content_id Titus content identifier.
     * @return signed_url_dto
     */
    public function get_signed_download_url(string $content_id): signed_url_dto {
        $url  = $this->baseurl . '/download.php';
        $body = json_encode(['content_id' => $content_id]);

        $response = $this->do_post($url, $body);
        $data     = $this->decode_json($response, $url);

        return new signed_url_dto(
            url:        $data['download_url'] ?? '',
            expires_in: (int)($data['expires_in'] ?? 300),
        );
    }

    /**
     * Download a file from a signed URL and write it to a local path.
     *
     * The URL host must be either the configured Titus API host or an AWS S3 host
     * to prevent open-redirect / SSRF exploitation.
     *
     * @param string $url      Signed download URL.
     * @param string $destpath Absolute filesystem path to write the file to.
     * @return void
     */
    public function download_to(string $url, string $destpath): void {
        $this->validate_download_url($url);

        make_writable_directory(dirname($destpath));

        // download_file_content with $tofile writes directly to disk and returns true on success.
        $result = download_file_content(
            $url,
            null,          // no extra headers needed for signed URLs
            null,          // GET request
            false,         // $fullresponse — not needed when writing to file
            320,           // timeout seconds
            20,            // connect timeout
            false,         // skip cert verify
            $destpath      // write directly to this path
        );

        if ($result === false || !file_exists($destpath) || filesize($destpath) === 0) {
            throw new titus_network_exception('Download failed or produced empty file: ' . $url);
        }
    }

    /**
     * Check whether the Titus API is reachable via its health endpoint.
     *
     * @return bool True if the API responds successfully.
     */
    public function health(): bool {
        try {
            $url = $this->baseurl . '/health.php';
            $this->do_get($url);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Private HTTP helpers
    // -------------------------------------------------------------------------

    /**
     * Perform a GET request with the licence key header.
     *
     * @param string $url Absolute URL.
     * @return string Response body.
     * @throws titus_network_exception  On cURL-level failure.
     * @throws titus_auth_exception     On HTTP 401/403.
     * @throws titus_not_found_exception On HTTP 404.
     * @throws titus_rate_limit_exception On HTTP 429.
     * @throws titus_server_exception   On HTTP 5xx.
     */
    private function do_get(string $url): string {
        $headers = ['X-Licence-Key: ' . $this->licencekey];

        $result = download_file_content($url, $headers, null, true);

        return $this->process_response($result, $url);
    }

    /**
     * Perform a POST request with a JSON body and the licence key header.
     *
     * @param string $url  Absolute URL.
     * @param string $body JSON-encoded request body.
     * @return string Response body.
     */
    private function do_post(string $url, string $body): string {
        $headers = [
            'X-Licence-Key: ' . $this->licencekey,
            'Content-Type: application/json',
        ];

        // download_file_content treats any non-null $postdata as a POST.
        $result = download_file_content($url, $headers, $body, true);

        return $this->process_response($result, $url);
    }

    /**
     * Inspect the full-response object returned by download_file_content and either
     * return the body string or throw the appropriate typed exception.
     *
     * @param \stdClass|false $result Return value of download_file_content with $fullresponse=true.
     * @param string          $url    Original URL (for error messages).
     * @return string Response body.
     */
    private function process_response($result, string $url): string {
        // cURL-level failure: $result can be false or have status 0/-100.
        if ($result === false) {
            throw new titus_network_exception('Request failed (cURL error): ' . $url);
        }

        $status = (int)$result->status;

        if ($status === 0 || $status === -100) {
            $error = $result->error ?? 'Unknown network error';
            throw new titus_network_exception("$error — $url");
        }

        if ($status === 401 || $status === 403) {
            throw new titus_auth_exception('Unauthorised', $status);
        }

        if ($status === 404) {
            throw new titus_not_found_exception('Not found: ' . $url, 404);
        }

        if ($status === 429) {
            $retry = $this->parse_header_value($result->headers, 'Retry-After', 60);
            throw new titus_rate_limit_exception('Rate limited', 429, (int)$retry);
        }

        if ($status >= 500) {
            throw new titus_server_exception('Server error', $status);
        }

        // Non-200 but not a recognised error — treat as generic API failure.
        if ($status !== 200) {
            throw new titus_api_exception('Unexpected HTTP status', $status);
        }

        return (string)($result->results ?? '');
    }

    /**
     * JSON-decode a response body and validate the shape.
     *
     * @param string $body The raw response string.
     * @param string $url  The request URL (for error context).
     * @return array Decoded associative array.
     * @throws titus_api_exception If the body is not valid JSON or contains an API-level error field.
     */
    private function decode_json(string $body, string $url): array {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new titus_api_exception('Invalid JSON response from: ' . $url);
        }

        if (isset($data['error'])) {
            throw new titus_api_exception((string)$data['error']);
        }

        return $data;
    }

    /**
     * Validate that a download URL's host is trusted (configured API host or AWS S3).
     *
     * This prevents SSRF attacks where a compromised API response redirects
     * downloads to an internal or attacker-controlled host.
     *
     * @param string $url URL to validate.
     * @return void
     * @throws titus_security_exception If the host is not trusted.
     */
    private function validate_download_url(string $url): void {
        $configuredhost = parse_url($this->baseurl, PHP_URL_HOST);
        $downloadhost   = parse_url($url, PHP_URL_HOST);

        $is_local = ($downloadhost !== null && $downloadhost === $configuredhost);
        $is_aws   = (bool)preg_match(
            '~^([a-z0-9-]+\.)?s3[.-][a-z0-9-]*\.amazonaws\.com$~i',
            $downloadhost ?? ''
        );

        if (!$is_local && !$is_aws) {
            throw new titus_security_exception("Untrusted download host: $downloadhost");
        }
    }

    /**
     * Parse a specific header value from the raw header lines array returned by
     * download_file_content.
     *
     * download_file_content returns ->headers as an array of raw header strings
     * (e.g. "Retry-After: 60\r\n"), NOT a parsed key-value map.
     *
     * @param array  $rawheaders Array of raw header lines.
     * @param string $name       Header name to look for (case-insensitive).
     * @param mixed  $default    Value to return if the header is not present.
     * @return string|mixed Trimmed header value, or $default.
     */
    private function parse_header_value(array $rawheaders, string $name, $default = null) {
        $prefix = strtolower($name) . ':';
        foreach ($rawheaders as $line) {
            if (strpos(strtolower($line), $prefix) === 0) {
                return trim(substr($line, strlen($prefix)));
            }
        }
        return $default;
    }
}
