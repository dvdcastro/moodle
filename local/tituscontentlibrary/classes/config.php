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

namespace local_tituscontentlibrary;

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration helper for local_tituscontentlibrary.
 *
 * Provides typed accessors for all plugin settings, including
 * transparent decryption of the stored licence key.
 */
class config {

    /**
     * Returns the decrypted licence key.
     *
     * @return string Decrypted licence key, or empty string if not set / decryption fails.
     */
    public static function get_licence_key(): string {
        $encrypted = get_config('local_tituscontentlibrary', 'licencekey');
        if (empty($encrypted)) {
            return '';
        }
        try {
            return \core\encryption::decrypt($encrypted);
        } catch (\Throwable $e) {
            debugging('Failed to decrypt Titus licence key: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return '';
        }
    }

    /**
     * Returns the API base URL (trailing slash stripped).
     *
     * @return string API base URL.
     */
    public static function get_api_base_url(): string {
        return rtrim((string) get_config('local_tituscontentlibrary', 'apibaseurl'), '/');
    }

    /**
     * Returns the catalogue cache TTL in seconds.
     *
     * @return int Cache TTL (defaults to 3600 if not set).
     */
    public static function get_cache_ttl(): int {
        // Note: 0 is a valid value ("no cache"), so fall back to the default only
        // when the setting is genuinely unset — not when it is the falsy value 0.
        $val = get_config('local_tituscontentlibrary', 'cachettl');
        return ($val !== false && $val !== '') ? (int) $val : 3600;
    }

    /**
     * Returns the number of content tiles to display per page in the marketplace.
     *
     * @return int Tiles per page (defaults to 12 if not set).
     */
    public static function get_tiles_per_page(): int {
        return (int) get_config('local_tituscontentlibrary', 'tilesperpage') ?: 12;
    }

    /**
     * Returns the default course category ID for imported courses.
     *
     * @return int Category ID (defaults to 1 if not set).
     */
    public static function get_default_category_id(): int {
        return (int) get_config('local_tituscontentlibrary', 'defaultcategoryid') ?: 1;
    }

    /** Add mode: background adhoc task. */
    const ADDMODE_ASYNC = 'async';

    /** Add mode: synchronous — blocks the web request until the course is created (default). */
    const ADDMODE_SYNC = 'sync';

    /**
     * Returns the configured add mode ('async' or 'sync'). Defaults to sync (decision D1, 2026-06-03):
     * the brief §5.4 specifies a synchronous add; async stays available as an opt-in for large packages.
     *
     * @return string One of the ADDMODE_* constants.
     */
    public static function get_add_mode(): string {
        $v = get_config('local_tituscontentlibrary', 'addmode');
        return $v === self::ADDMODE_ASYNC ? self::ADDMODE_ASYNC : self::ADDMODE_SYNC;
    }
}
