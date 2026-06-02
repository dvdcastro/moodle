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

defined('MOODLE_INTERNAL') || die();

/**
 * Validates a downloaded SCORM ZIP before it is imported into Moodle.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scorm_zip_validator {

    /**
     * Assert that a path points to a valid, non-empty SCORM ZIP (contains imsmanifest.xml).
     *
     * @param string $path Absolute path to the downloaded file.
     * @throws \local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception
     */
    public static function validate(string $path): void {
        global $CFG;

        if (!file_exists($path) || filesize($path) === 0) {
            throw new \local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception(
                'Downloaded file is empty or missing'
            );
        }
        if (!empty($CFG->maxbytes) && filesize($path) > $CFG->maxbytes) {
            throw new \local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception(
                'ZIP exceeds maxbytes limit'
            );
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception(
                'Cannot open ZIP file'
            );
        }
        $has_manifest = $zip->locateName('imsmanifest.xml') !== false;
        $zip->close();
        if (!$has_manifest) {
            throw new \local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception(
                'imsmanifest.xml not found in ZIP root'
            );
        }
    }
}
