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
 * Callback fired after the Titus licence key setting is saved.
 *
 * Attempts a lightweight connection to the Titus API catalogue endpoint
 * and shows a success notification if the API responds with HTTP 200.
 * Failures are silently ignored so a bad key never prevents saving.
 */
function local_tituscontentlibrary_validate_connexion(): void {
    $apiurl = \local_tituscontentlibrary\config::get_api_base_url();
    $key    = \local_tituscontentlibrary\config::get_licence_key();
    if (empty($apiurl) || empty($key)) {
        return;
    }
    // Test the catalogue endpoint.
    $testurl = $apiurl . '/api/catalogue.php';
    $headers = ['X-Licence-Key: ' . $key];
    $response = download_file_content($testurl, $headers, null, true, 5, 5);
    if ($response && isset($response->status) && $response->status == '200') {
        \core\notification::success('Titus API connection verified.');
    }
    // Silently ignore failures (simulator may not have /health endpoint).
}
