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
 * Exception thrown when the Titus API forbids access to a resource (HTTP 403).
 *
 * Distinct from {@see titus_auth_exception} (HTTP 401): authentication succeeded
 * but the licence is not permitted to access the requested content (e.g. the
 * content is not in the site's subscription tier — see brief §6.2).
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class titus_forbidden_exception extends titus_api_exception {

    public function __construct(string $message, int $http_code = 403, ?\Throwable $previous = null) {
        $detail = $message . ($http_code ? " (HTTP $http_code)" : '');
        // 'error:apiforbidden' has no {$a}; keep $detail as debuginfo for the logs.
        \moodle_exception::__construct('error:apiforbidden', 'local_tituscontentlibrary', '', null, $detail . ($previous ? ' | ' . $previous->getMessage() : ''));
    }
}
