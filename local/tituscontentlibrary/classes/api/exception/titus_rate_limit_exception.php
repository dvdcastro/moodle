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
 * Exception thrown when the Titus API returns HTTP 429 Too Many Requests.
 *
 * Callers can read $retry_after to know how many seconds to wait before retrying.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class titus_rate_limit_exception extends titus_api_exception {

    /**
     * @param string          $message     Human-readable description.
     * @param int             $http_code   HTTP status code (typically 429).
     * @param int             $retry_after Seconds to wait before retrying.
     * @param \Throwable|null $previous    Previous exception for chaining.
     */
    public function __construct(
        string $message,
        int $http_code = 429,
        public readonly int $retry_after = 60,
        ?\Throwable $previous = null,
    ) {
        $detail = $message . ($http_code ? " (HTTP $http_code)" : '');
        \moodle_exception::__construct('error:apiratelimit', 'local_tituscontentlibrary', '', null, $detail);
    }
}
