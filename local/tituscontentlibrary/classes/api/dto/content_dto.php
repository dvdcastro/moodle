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

namespace local_tituscontentlibrary\api\dto;

defined('MOODLE_INTERNAL') || die();

/**
 * Data Transfer Object representing a single Titus content item.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class content_dto {

    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
        public readonly string $short_description,
        public readonly string $thumbnail_url,
        public readonly string $category,
        public readonly array  $tags,
        public readonly int    $duration_minutes,
        public readonly bool   $is_featured,
        public readonly bool   $is_new,
        public readonly string $subcategory = '',
        public readonly string $version = '1.0.0',
        public readonly ?int   $updated_at = null,
    ) {}

    /**
     * Construct a content_dto from a raw API response array.
     *
     * Accepts both 'content_id' (API canonical) and 'id' as the primary identifier.
     *
     * @param array $a Raw associative array from JSON decode.
     * @return self
     */
    public static function from_array(array $a): self {
        return new self(
            id:                (string)($a['content_id'] ?? $a['id'] ?? ''),
            title:             clean_param($a['title'] ?? '', PARAM_TEXT),
            description:       clean_param($a['description'] ?? '', PARAM_TEXT),
            short_description: clean_param($a['short_description'] ?? '', PARAM_TEXT),
            thumbnail_url:     clean_param($a['thumbnail_url'] ?? '', PARAM_URL),
            category:          clean_param($a['category'] ?? '', PARAM_TEXT),
            subcategory:       clean_param($a['subcategory'] ?? '', PARAM_TEXT),
            tags:              array_values(array_map(
                fn($t) => clean_param((string)$t, PARAM_TEXT),
                (array)($a['tags'] ?? [])
            )),
            duration_minutes:  (int)($a['duration_minutes'] ?? 0),
            version:           clean_param($a['version'] ?? '1.0.0', PARAM_TEXT),
            is_featured:       (bool)($a['is_featured'] ?? false),
            is_new:            (bool)($a['is_new'] ?? false),
            updated_at:        isset($a['updated_at']) ? (
                is_numeric($a['updated_at']) ? (int)$a['updated_at'] : (strtotime($a['updated_at']) ?: null)
            ) : null,
        );
    }
}
