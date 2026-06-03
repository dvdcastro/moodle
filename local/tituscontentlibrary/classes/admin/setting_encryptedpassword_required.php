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

namespace local_tituscontentlibrary\admin;

defined('MOODLE_INTERNAL') || die();

/**
 * Encrypted password admin setting that is required (must not be empty).
 *
 * Core's {@see \admin_setting_encryptedpassword} has no notion of a required
 * value — its write_setting() accepts an empty string. The licence key is
 * mandatory for the plugin to operate (brief §5.1), so this subclass rejects
 * an empty value with an inline error, mirroring the validate() pattern used by
 * admin_setting_configtext.
 *
 * Note: because the field never echoes the stored value back to the form, an
 * empty submission is only treated as "clearing" when there is no value stored
 * yet. If a value is already stored, an empty submission leaves it untouched
 * (core behaviour), so the required constraint is enforced for the initial save.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class setting_encryptedpassword_required extends \admin_setting_encryptedpassword {

    /**
     * Write the setting, enforcing the required constraint and validating the
     * licence key against the Titus API before accepting it (MLFR-225).
     *
     * A rejected key (HTTP 401/403) is rolled back and reported as an inline error
     * string, which Moodle renders on the settings form without persisting the bad
     * value — no full error page. A network/unreachable error does NOT block the
     * save (brief §5.1); it is saved and a warning is shown. A verified key is
     * saved and a success notice is shown.
     *
     * @param string $data Submitted value.
     * @return string Empty string on success, error message on failure.
     */
    public function write_setting($data) {
        $data = trim((string) $data);

        // Reject an empty value only when nothing is stored yet — this enforces
        // the "required" constraint on the initial save without forcing the admin
        // to re-enter the key on every settings save (the field is write-only).
        if ($data === '' && (string) $this->get_setting() === '') {
            return get_string('licencekey:required', 'local_tituscontentlibrary');
        }

        // Write-only field: an empty submission with a value already stored leaves
        // the existing key untouched (core behaviour) and needs no re-validation.
        if ($data === '') {
            return parent::write_setting($data);
        }

        // Persist the candidate first so the API client (which reads the stored key)
        // probes with the new value; capture the previous value to roll back on
        // rejection.
        $previous = get_config('local_tituscontentlibrary', 'licencekey');
        $error    = parent::write_setting($data);
        if ($error !== '') {
            return $error;
        }

        $status = \local_tituscontentlibrary\local\connexion_validator::probe();

        if ($status === \local_tituscontentlibrary\local\connexion_validator::INVALID) {
            // Roll back the rejected key and block the save with an inline error.
            if ($previous === false) {
                unset_config('licencekey', 'local_tituscontentlibrary');
            } else {
                set_config('licencekey', $previous, 'local_tituscontentlibrary');
            }
            return get_string('connexion:invalid_key', 'local_tituscontentlibrary');
        }

        if ($status === \local_tituscontentlibrary\local\connexion_validator::UNREACHABLE) {
            \core\notification::warning(get_string('connexion:unreachable', 'local_tituscontentlibrary'));
        } else {
            \core\notification::success(get_string('connexion:ok', 'local_tituscontentlibrary'));
        }

        return '';
    }
}
