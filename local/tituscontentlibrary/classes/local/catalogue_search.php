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

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * Pure filter helper for the Titus content catalogue.
 *
 * All methods are stateless; they receive and return arrays of content_dto.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_search {

    /**
     * Filter catalogue items by a free-text query and/or an exact category slug.
     *
     * Text search matches title, short_description, and any tag (case-insensitive,
     * substring match — mirrors the client-side search.js behaviour).
     *
     * @param content_dto[] $items    Full catalogue.
     * @param string        $query    Free-text search string (empty = no filter).
     * @param string        $category Category slug to filter by (empty = no filter).
     * @return content_dto[]
     */
    public static function filter(array $items, string $query, string $category): array {
        $lc       = \core_text::strtolower(trim($query));
        $catslug  = trim($category);

        return array_values(array_filter($items, function(content_dto $item) use ($lc, $catslug) {
            // Category filter: exact slug comparison.
            if ($catslug !== '') {
                $itemslug = preg_replace('/[^a-z0-9]/', '', \core_text::strtolower($item->category));
                if ($itemslug !== $catslug) {
                    return false;
                }
            }

            // Text filter: title, short_description, tags.
            if ($lc !== '') {
                $intitle = \core_text::strpos(\core_text::strtolower($item->title), $lc) !== false;
                $indesc  = \core_text::strpos(\core_text::strtolower($item->short_description), $lc) !== false;
                $intags  = false;
                foreach ($item->tags as $tag) {
                    if (\core_text::strpos(\core_text::strtolower($tag), $lc) !== false) {
                        $intags = true;
                        break;
                    }
                }
                if (!$intitle && !$indesc && !$intags) {
                    return false;
                }
            }

            return true;
        }));
    }
}
