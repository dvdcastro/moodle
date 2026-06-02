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

/**
 * Web service function definitions for local_tituscontentlibrary.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_tituscontentlibrary_add_course' => [
        'classname'     => 'local_tituscontentlibrary\\external\\add_course',
        'description'   => 'Queue a Titus content item for import as a Moodle course',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/tituscontentlibrary:addcourse',
    ],
    'local_tituscontentlibrary_get_catalogue' => [
        'classname'     => 'local_tituscontentlibrary\\external\\get_catalogue',
        'description'   => 'Return the cached Titus content catalogue',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/tituscontentlibrary:viewlibrary',
    ],
    'local_tituscontentlibrary_get_add_status' => [
        'classname'     => 'local_tituscontentlibrary\\external\\get_add_status',
        'description'   => 'Return the current status of a queued Titus content import',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/tituscontentlibrary:viewlibrary',
    ],
    'local_tituscontentlibrary_resync_course' => [
        'classname'     => 'local_tituscontentlibrary\\external\\resync_course',
        'description'   => 'Queue a re-sync of a previously imported Titus SCORM package',
        'type'          => 'write',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/tituscontentlibrary:manageplugin',
    ],
    'local_tituscontentlibrary_search_catalogue' => [
        'classname'     => 'local_tituscontentlibrary\\external\\search_catalogue',
        'description'   => 'Filter and sort the cached Titus catalogue server-side',
        'type'          => 'read',
        'ajax'          => true,
        'loginrequired' => true,
        'capabilities'  => 'local/tituscontentlibrary:viewlibrary',
    ],
];
