<?php
/**
 * Internal-network API client for smoke tests.
 *
 * Implements titus_api_client_interface using $CFG->wwwroot (the ngrok URL when
 * running under Docker with sslproxy=true) so that all requests go through the
 * same hostname Moodle was configured with, avoiding redirecterrordetected errors.
 *
 * NOT for production use — no SSRF validation.
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;

class smoke_titus_api_client implements titus_api_client_interface {

    private string $licencekey;
    private string $apibase;

    public function __construct() {
        global $CFG;
        $this->licencekey = \local_tituscontentlibrary\config::get_licence_key();
        // Use wwwroot so requests match Moodle's configured hostname.
        // Using 'http://localhost' or 'http://webserver' causes redirecterrordetected
        // when $CFG->wwwroot is an HTTPS ngrok URL with sslproxy=true.
        $this->apibase = rtrim($CFG->wwwroot, '/') . '/local/titusclsim/api';
    }

    public static function get_base(): string {
        global $CFG;
        return rtrim($CFG->wwwroot, '/') . '/local/titusclsim/api';
    }

    public function get_catalogue(array $filters = []): array {
        $url = $this->apibase . '/catalogue.php';
        if (!empty($filters)) {
            $url .= '?' . http_build_query($filters);
        }
        $body = $this->do_get($url);
        $data = json_decode($body, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON from catalogue endpoint');
        }
        // API response per brief: {"tier": "...", "content": [...]}
        $items = isset($data['content']) && is_array($data['content'])
            ? $data['content']
            : $data;
        return array_map([content_dto::class, 'from_array'], $items);
    }

    public function get_signed_download_url(string $content_id): signed_url_dto {
        $url  = $this->apibase . '/download.php';
        $body = json_encode(['content_id' => $content_id]);
        $resp = $this->do_post($url, $body);
        $data = json_decode($resp, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON from download endpoint');
        }
        return new signed_url_dto(
            url:        $data['url'] ?? '',
            filename:   $data['filename'] ?? '',
            expires_in: (int)($data['expires_in'] ?? 300),
        );
    }

    public function download_to(string $url, string $destpath): void {
        make_writable_directory(dirname($destpath));
        $result = download_file_content($url, null, null, false, 320, 20, false, $destpath);
        if ($result === false || !file_exists($destpath) || filesize($destpath) === 0) {
            throw new \RuntimeException('Smoke download failed or produced empty file: ' . $url);
        }
    }

    public function health(): bool {
        try {
            $this->do_get($this->apibase . '/catalogue.php?per_page=1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function do_get(string $url): string {
        global $CFG;
        $result = download_file_content(
            $url,
            ['X-Licence-Key: ' . $this->licencekey, 'X-Site-URL: ' . $CFG->wwwroot],
            null,
            true
        );
        return $this->process($result, $url);
    }

    private function do_post(string $url, string $body): string {
        global $CFG;
        $result = download_file_content(
            $url,
            ['X-Licence-Key: ' . $this->licencekey, 'X-Site-URL: ' . $CFG->wwwroot, 'Content-Type: application/json'],
            $body,
            true
        );
        return $this->process($result, $url);
    }

    private function process($result, string $url): string {
        if ($result === false) {
            throw new \RuntimeException("Network error (cURL failed): {$url}");
        }
        $status = (int)$result->status;
        if ($status === 0 || $status === -100) {
            throw new \RuntimeException(($result->error ?? 'Unknown error') . " — {$url}");
        }
        if ($status === 401 || $status === 403) {
            throw new \local_tituscontentlibrary\api\exception\titus_auth_exception('Unauthorised', $status);
        }
        if ($status === 404) {
            throw new \local_tituscontentlibrary\api\exception\titus_not_found_exception("Not found: {$url}", 404);
        }
        if ($status !== 200) {
            throw new \RuntimeException("HTTP {$status}: {$url}");
        }
        return (string)($result->results ?? '');
    }
}

/**
 * A smoke API client that always fails on get_catalogue().
 * Used by smoke_test_06 to simulate API outage without needing a bad network URL.
 */
class smoke_failing_api_client implements titus_api_client_interface {
    public function get_catalogue(array $filters = []): array {
        throw new \RuntimeException('Simulated API failure for monitoring test');
    }
    public function get_signed_download_url(string $content_id): signed_url_dto {
        throw new \RuntimeException('Not used in monitoring test');
    }
    public function download_to(string $url, string $destpath): void {
        throw new \RuntimeException('Not used in monitoring test');
    }
    public function health(): bool {
        return false;
    }
}
