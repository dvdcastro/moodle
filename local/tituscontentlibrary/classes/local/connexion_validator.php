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

namespace local_tituscontentlibrary\local;

use local_tituscontentlibrary\config;
use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\exception\titus_auth_exception;
use local_tituscontentlibrary\api\exception\titus_forbidden_exception;

/**
 * Probes the Titus API with the currently-stored licence key.
 *
 * Pure status probe with no side effects (no notifications, no config writes) so
 * it can be reused by the settings validator (MLFR-225) and unit-tested in
 * isolation via {@see client_factory::set_test_client()}. URL-building is owned
 * by the API client, keeping this probe in lock-step with the Add/sync pipeline.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class connexion_validator {

    /** Connection verified — key accepted. */
    const OK = 'ok';

    /** Key rejected (HTTP 401/403) — the caller must block the save. */
    const INVALID = 'invalid';

    /** Network error / unreachable / unconfigured — the caller must NOT block. */
    const UNREACHABLE = 'unreachable';

    /**
     * Probe the API with the stored licence key and classify the outcome.
     *
     * @return string One of self::OK, self::INVALID or self::UNREACHABLE.
     */
    public static function probe(): string {
        $apiurl = config::get_api_base_url();
        $key    = config::get_licence_key();
        if (empty($apiurl) || empty($key)) {
            // Nothing to probe — treat as unverifiable rather than rejected.
            return self::UNREACHABLE;
        }

        $client = client_factory::get_client();
        try {
            // A successful catalogue fetch with the configured key proves both the
            // connection and the key. We probe the catalogue directly rather than via
            // health() (which swallows auth errors and returns a bare bool) so we can
            // classify a rejected key apart from a transient network error in a single
            // round-trip — only the former may block the save (brief §5.1).
            $client->get_catalogue(['per_page' => 1]);
            return self::OK;
        } catch (titus_auth_exception $e) {
            return self::INVALID;
        } catch (titus_forbidden_exception $e) {
            // 403 = key recognised but not permitted; still a key problem to fix.
            return self::INVALID;
        } catch (\Throwable $e) {
            // Network error / unreachable / unexpected response — do not block.
            return self::UNREACHABLE;
        }
    }
}
