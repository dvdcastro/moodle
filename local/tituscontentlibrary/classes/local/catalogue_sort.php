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
 * Pure sort helper for the Titus content catalogue.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_sort {

    /** Valid sort keys. */
    const VALID_SORTS = ['az', 'za', 'newest', 'duration', 'category', 'featured', 'new'];

    /**
     * Sort a catalogue array in-place and return the result.
     *
     * @param content_dto[] $items Catalogue items.
     * @param string        $sort  Sort key; invalid values fall back to 'az'.
     * @return content_dto[]
     */
    public static function sort(array $items, string $sort): array {
        if (!in_array($sort, self::VALID_SORTS, true)) {
            $sort = 'az';
        }
        usort($items, self::comparator($sort));
        return $items;
    }

    /**
     * Build a comparator closure for the requested sort key.
     *
     * @param string $sort
     * @return callable
     */
    private static function comparator(string $sort): callable {
        switch ($sort) {
            case 'za':
                return fn(content_dto $a, content_dto $b) => \core_text::strtolower($b->title) <=> \core_text::strtolower($a->title);
            case 'newest':
                // Nulls sort last.
                return function(content_dto $a, content_dto $b) {
                    if ($a->updated_at === null && $b->updated_at === null) {
                        return 0;
                    }
                    if ($a->updated_at === null) {
                        return 1;
                    }
                    if ($b->updated_at === null) {
                        return -1;
                    }
                    return $b->updated_at <=> $a->updated_at;
                };
            case 'duration':
                return fn(content_dto $a, content_dto $b) => $a->duration_minutes <=> $b->duration_minutes;
            case 'category':
                return fn(content_dto $a, content_dto $b) => \core_text::strtolower($a->category) <=> \core_text::strtolower($b->category);
            case 'featured':
                // Featured items first, then by title.
                return function(content_dto $a, content_dto $b) {
                    if ($a->is_featured !== $b->is_featured) {
                        return $b->is_featured <=> $a->is_featured;
                    }
                    return \core_text::strtolower($a->title) <=> \core_text::strtolower($b->title);
                };
            case 'new':
                // New items first, then by title.
                return function(content_dto $a, content_dto $b) {
                    if ($a->is_new !== $b->is_new) {
                        return $b->is_new <=> $a->is_new;
                    }
                    return \core_text::strtolower($a->title) <=> \core_text::strtolower($b->title);
                };
            default: // 'az'
                return fn(content_dto $a, content_dto $b) => \core_text::strtolower($a->title) <=> \core_text::strtolower($b->title);
        }
    }
}
