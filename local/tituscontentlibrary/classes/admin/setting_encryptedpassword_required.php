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
     * Write the setting, rejecting an empty value when none is stored yet.
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

        return parent::write_setting($data);
    }
}
