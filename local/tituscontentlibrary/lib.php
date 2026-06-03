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

// Licence-key validation now lives in the setting's write_setting() so a rejected
// key is blocked before it is persisted and reported as an inline form error
// (MLFR-225). See \local_tituscontentlibrary\admin\setting_encryptedpassword_required
// and \local_tituscontentlibrary\local\connexion_validator.
