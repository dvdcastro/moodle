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
        return (int) get_config('local_tituscontentlibrary', 'cachettl') ?: 3600;
    }

    /**
     * Returns the default course category ID for imported courses.
     *
     * @return int Category ID (defaults to 1 if not set).
     */
    public static function get_default_category_id(): int {
        return (int) get_config('local_tituscontentlibrary', 'defaultcategoryid') ?: 1;
    }
}
