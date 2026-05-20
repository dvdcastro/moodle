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

namespace local_tituscontentlibrary\api\exception;

defined('MOODLE_INTERNAL') || die();

/**
 * Base exception for all Titus API errors.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class titus_api_exception extends \moodle_exception {

    /**
     * @param string          $message   Human-readable error description.
     * @param int             $http_code HTTP status code (0 if not applicable).
     * @param \Throwable|null $previous  Previous exception for chaining.
     */
    public function __construct(string $message, int $http_code = 0, ?\Throwable $previous = null) {
        $detail = $message . ($http_code ? " (HTTP $http_code)" : '');
        parent::__construct('error:apifailure', 'local_tituscontentlibrary', '', null, $detail);
    }
}
