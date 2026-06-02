<?php
namespace local_tituscontentlibrary\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Centralised factory for the Titus API client.
 *
 * Tests call set_test_client() to inject a mock; production code calls get_client()
 * and receives the real HTTP client. reset() clears any injected override.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_factory {

    /** @var titus_api_client_interface|null Test override. */
    private static ?titus_api_client_interface $override = null;

    public static function get_client(): titus_api_client_interface {
        return self::$override ?? new titus_api_client();
    }

    public static function set_test_client(?titus_api_client_interface $client): void {
        self::$override = $client;
    }

    public static function reset(): void {
        self::$override = null;
    }
}
