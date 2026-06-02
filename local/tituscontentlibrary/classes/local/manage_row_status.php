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
 * Derives the display status of an added-content row for the manage page.
 *
 * Display status combines the raw DB status with live signals (course existence,
 * catalogue membership) to surface orphan and out-of-date conditions.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_row_status {

    const OK             = 'ok';
    const COURSE_DELETED = 'coursedeleted';
    const OUT_OF_DATE    = 'outofdate';
    const FAILED         = 'failed';
    const PROCESSING     = 'processing';
    const PENDING        = 'pending';

    /**
     * Derive the display status for a single added row.
     *
     * @param object $row         Row from local_tituscontentlibrary_added.
     * @param bool   $courseexists Whether the linked course still exists.
     * @param array  $catalogueids Indexed array of current catalogue content IDs.
     * @return string One of the class constants.
     */
    public static function derive(object $row, bool $courseexists, array $catalogueids): string {
        if ($row->status === content_status::FAILED) {
            return self::FAILED;
        }
        if ($row->status === content_status::PROCESSING) {
            return self::PROCESSING;
        }
        if ($row->status === content_status::PENDING) {
            return self::PENDING;
        }
        // Row is COMPLETED — check for orphan conditions.
        if (!$courseexists) {
            return self::COURSE_DELETED;
        }
        if (!in_array($row->contentid, $catalogueids, true)) {
            return self::OUT_OF_DATE;
        }
        return self::OK;
    }
}
