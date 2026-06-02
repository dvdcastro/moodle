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

defined('MOODLE_INTERNAL') || die();

/**
 * Declare user preferences managed by this plugin.
 *
 * Required so core_user\external\set_user_preferences will accept writes to
 * our preference keys from AJAX calls.
 *
 * @return array
 */
function local_tituscontentlibrary_user_preferences(): array {
    return [
        'local_tituscontentlibrary_sort' => [
            'null'    => NULL_NOT_ALLOWED,
            'default' => 'az',
            'type'    => PARAM_ALPHA,
        ],
    ];
}

/**
 * Callback fired after the Titus licence key setting is saved.
 *
 * Attempts a lightweight connection to the Titus API catalogue endpoint and:
 *  - shows a success notification if the API responds with HTTP 200;
 *  - throws a \moodle_exception if the API confirms the key is invalid
 *    (HTTP 401/403), which aborts the settings save so a rejected key is
 *    never persisted (brief §5.1: "show an error if the key is rejected").
 *
 * A network error / unreachable API does NOT block the save — only a
 * confirmed 401/403 from the API does. This keeps the admin able to save
 * configuration during transient outages.
 *
 * @throws \moodle_exception When the API confirms the key is invalid (401/403).
 */
function local_tituscontentlibrary_validate_connexion(): void {
    $apiurl = \local_tituscontentlibrary\config::get_api_base_url();
    $key    = \local_tituscontentlibrary\config::get_licence_key();
    if (empty($apiurl) || empty($key)) {
        return;
    }

    // Delegate to the API client rather than building a URL here. The client owns
    // the canonical URL-building logic (e.g. /catalogue.php vs /catalogue for the
    // simulator vs the real API), so reusing it keeps "Test connection" in lock-step
    // with the endpoints the Add/sync pipeline actually calls (MLFR-252).
    $client = \local_tituscontentlibrary\api\client_factory::get_client();

    try {
        // health() probes the catalogue endpoint with the configured key/headers and
        // returns true on HTTP 200. It swallows auth errors internally, so we run an
        // explicit catalogue probe below to distinguish "invalid key" from "unreachable".
        if ($client->health()) {
            \core\notification::success(get_string('connexion:ok', 'local_tituscontentlibrary'));
            return;
        }

        // health() returned false — re-probe to surface the underlying cause so we can
        // tell a rejected key (must block the save) apart from a network error (must not).
        $client->get_catalogue(['per_page' => 1]);

        // Reached the API without throwing but health() still failed — treat as a
        // transient/unexpected condition and warn without blocking the save.
        \core\notification::warning(get_string('connexion:unreachable', 'local_tituscontentlibrary'));
    } catch (\local_tituscontentlibrary\api\exception\titus_auth_exception $e) {
        // Confirmed invalid/rejected key — abort the save so the bad key is not persisted.
        //
        // The updatedcallback fires from admin_setting::post_write_settings() AFTER the
        // new value has already been written to config, so throwing alone would still
        // leave the rejected key stored. Unset the just-written value before throwing.
        unset_config('licencekey', 'local_tituscontentlibrary');
        throw new \moodle_exception('connexion:invalid_key', 'local_tituscontentlibrary');
    } catch (\local_tituscontentlibrary\api\exception\titus_forbidden_exception $e) {
        // 403 = key recognised but not permitted for this resource/tier. Still a key
        // problem the admin must fix, so treat it like a rejected key.
        unset_config('licencekey', 'local_tituscontentlibrary');
        throw new \moodle_exception('connexion:invalid_key', 'local_tituscontentlibrary');
    } catch (\Throwable $e) {
        // Network error / unreachable API / unexpected response — do NOT block the save
        // (brief §5.1), just warn the admin that the connection could not be verified.
        \core\notification::warning(get_string('connexion:unreachable', 'local_tituscontentlibrary'));
    }
}
