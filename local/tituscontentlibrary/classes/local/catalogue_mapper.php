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
 * Shared mapper from content_dto to WS/template array shape.
 *
 * Centralises the DTO → array conversion so get_catalogue and search_catalogue
 * always return identical field shapes.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_mapper {

    /**
     * Convert a content_dto to an array suitable for WS responses and templates.
     *
     * @param content_dto $dto
     * @return array
     */
    public static function dto_to_array(content_dto $dto): array {
        return [
            'id'               => $dto->id,
            'title'            => $dto->title,
            'description'      => $dto->description,
            'shortdescription' => $dto->short_description,
            'thumbnailurl'     => $dto->thumbnail_url,
            'category'         => $dto->category,
            'tags'             => $dto->tags,
            'subcategory'      => $dto->subcategory,
            'version'          => $dto->version,
            'durationminutes'  => $dto->duration_minutes,
            'isfeatured'       => $dto->is_featured,
            'isnew'            => $dto->is_new,
            'publishedat'      => $dto->updated_at,
            'updatedat'        => $dto->updated_at ? userdate($dto->updated_at, get_string('strftimedate', 'langconfig')) : '',
        ];
    }
}
