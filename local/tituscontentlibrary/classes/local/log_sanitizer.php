<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\config;

/**
 * Sanitizes strings that may contain sensitive values before they reach logs.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class log_sanitizer {

    /**
     * Replace occurrences of the licence key in $message with '***'.
     *
     * @param string $message Raw log/error string.
     * @return string Sanitized string safe for logging.
     */
    public static function sanitize(string $message): string {
        return self::sanitize_with_key($message, config::get_licence_key());
    }

    /**
     * Replace $key inside $message with '***'. Testable without config.
     *
     * @param string $message String to sanitize.
     * @param string $key     Secret value to mask.
     * @return string
     */
    public static function sanitize_with_key(string $message, string $key): string {
        if ($key === '') {
            return $message;
        }
        return str_replace($key, '***', $message);
    }
}
