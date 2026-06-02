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

namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_tituscontentlibrary\local\catalogue_manager;
use local_tituscontentlibrary\local\catalogue_mapper;
use local_tituscontentlibrary\local\catalogue_search;
use local_tituscontentlibrary\local\catalogue_sort;

/**
 * WS function: server-side filter + sort over the cached catalogue.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class search_catalogue extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query'    => new external_value(PARAM_TEXT, 'Free-text search query', VALUE_DEFAULT, ''),
            'category' => new external_value(PARAM_ALPHANUMEXT, 'Category slug filter', VALUE_DEFAULT, ''),
            'sort'     => new external_value(PARAM_ALPHA, 'Sort key', VALUE_DEFAULT, 'az'),
        ]);
    }

    public static function execute(string $query = '', string $category = '', string $sort = 'az'): array {
        ['query' => $query, 'category' => $category, 'sort' => $sort] =
            self::validate_parameters(self::execute_parameters(), compact('query', 'category', 'sort'));

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/tituscontentlibrary:viewlibrary', $ctx);

        $cm    = new catalogue_manager();
        $items = $cm->get_catalogue();
        $stale = $cm->is_stale();

        $items = catalogue_search::filter($items, $query, $category);
        $items = catalogue_sort::sort($items, $sort);

        $result = [];
        foreach ($items as $dto) {
            $result[] = catalogue_mapper::dto_to_array($dto);
        }

        return ['items' => $result, 'stale' => $stale];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'id'               => new external_value(PARAM_ALPHANUMEXT, 'Content ID'),
                    'title'            => new external_value(PARAM_TEXT, 'Title'),
                    'description'      => new external_value(PARAM_TEXT, 'Long description'),
                    'shortdescription' => new external_value(PARAM_TEXT, 'Short description'),
                    'thumbnailurl'     => new external_value(PARAM_URL, 'Thumbnail URL'),
                    'category'         => new external_value(PARAM_TEXT, 'Category'),
                    'tags'             => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Tag')
                    ),
                    'durationminutes'  => new external_value(PARAM_INT, 'Duration in minutes'),
                    'isfeatured'       => new external_value(PARAM_BOOL, 'Whether item is featured'),
                    'isnew'            => new external_value(PARAM_BOOL, 'Whether item is new'),
                    'publishedat'      => new external_value(PARAM_INT, 'Unix timestamp of publication', VALUE_OPTIONAL, null, NULL_ALLOWED),
                ])
            ),
            'stale' => new external_value(PARAM_BOOL, 'True if catalogue data may be outdated'),
        ]);
    }
}
