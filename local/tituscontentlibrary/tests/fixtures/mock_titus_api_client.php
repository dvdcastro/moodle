<?php
// Not autoloaded — tests must require_once this file explicitly.
namespace local_tituscontentlibrary\tests\fixtures;

use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;
use local_tituscontentlibrary\api\titus_api_client_interface;

/**
 * Fluent, call-tracking mock for titus_api_client_interface.
 *
 * Usage:
 *   $mock = (new mock_titus_api_client())
 *       ->with_catalogue([$dto])
 *       ->with_zip_fixture('/path/to/valid.zip');
 */
class mock_titus_api_client implements titus_api_client_interface {

    /** @var content_dto[] Items returned by get_catalogue(). */
    private array $catalogue = [];

    /** @var string Signed URL returned by get_signed_download_url(). */
    private string $signed_url = 'https://s3.amazonaws.com/fake/file.zip';

    /** @var string Filename returned with signed URL. */
    private string $signed_filename = 'file.zip';

    /** @var int Signed URL TTL. */
    private int $signed_expires = 300;

    /** @var string|null If set, copied to $destpath in download_to(). */
    private ?string $zip_fixture = null;

    /** @var array<string, \Throwable> Per-method exceptions. */
    private array $fail_on = [];

    /** @var array<string, int> Call counts per method. */
    private array $calls = [];

    // ------------------------------------------------------------------
    // Fluent builder methods
    // ------------------------------------------------------------------

    public function with_catalogue(array $items): self {
        $this->catalogue = $items;
        return $this;
    }

    public function with_signed_url(string $url, int $expires = 300, string $filename = 'content.zip'): self {
        $this->signed_url      = $url;
        $this->signed_expires  = $expires;
        $this->signed_filename = $filename;
        return $this;
    }

    public function with_zip_fixture(string $path): self {
        $this->zip_fixture = $path;
        return $this;
    }

    public function fail_on(string $method, \Throwable $exception): self {
        $this->fail_on[$method] = $exception;
        return $this;
    }

    // ------------------------------------------------------------------
    // Inspection
    // ------------------------------------------------------------------

    public function get_call_count(string $method): int {
        return $this->calls[$method] ?? 0;
    }

    public function get_calls(): array {
        return $this->calls;
    }

    // ------------------------------------------------------------------
    // Interface implementation
    // ------------------------------------------------------------------

    public function get_catalogue(array $filters = []): array {
        $this->calls['get_catalogue'] = ($this->calls['get_catalogue'] ?? 0) + 1;
        if (isset($this->fail_on['get_catalogue'])) {
            throw $this->fail_on['get_catalogue'];
        }
        return $this->catalogue;
    }

    public function get_signed_download_url(string $content_id): signed_url_dto {
        $this->calls['get_signed_download_url'] = ($this->calls['get_signed_download_url'] ?? 0) + 1;
        if (isset($this->fail_on['get_signed_download_url'])) {
            throw $this->fail_on['get_signed_download_url'];
        }
        return new signed_url_dto($this->signed_url, $this->signed_filename, $this->signed_expires);
    }

    public function download_to(string $url, string $destpath): void {
        $this->calls['download_to'] = ($this->calls['download_to'] ?? 0) + 1;
        if (isset($this->fail_on['download_to'])) {
            throw $this->fail_on['download_to'];
        }
        if ($this->zip_fixture !== null) {
            copy($this->zip_fixture, $destpath);
        } else {
            // Write an empty file so callers don't crash on file_exists checks.
            file_put_contents($destpath, '');
        }
    }

    public function health(): bool {
        $this->calls['health'] = ($this->calls['health'] ?? 0) + 1;
        if (isset($this->fail_on['health'])) {
            return false;
        }
        return true;
    }
}
