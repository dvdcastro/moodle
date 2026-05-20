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

namespace local_tituscontentlibrary\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Interface for the Titus Content Library API client.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface titus_api_client_interface {
    /**
     * Fetch the full content catalogue from the Titus API.
     *
     * @param array $filters Optional query-string filters (e.g. ['category' => 'leadership']).
     * @return \local_tituscontentlibrary\api\dto\content_dto[]
     */
    public function get_catalogue(array $filters = []): array;

    /**
     * Request a signed download URL for a given content item.
     *
     * @param string $content_id Titus content identifier.
     * @return \local_tituscontentlibrary\api\dto\signed_url_dto
     */
    public function get_signed_download_url(string $content_id): \local_tituscontentlibrary\api\dto\signed_url_dto;

    /**
     * Download a file from a signed URL and save it to a local path.
     *
     * @param string $url      Signed download URL (must be on a trusted host).
     * @param string $destpath Absolute path to write the file to.
     * @return void
     */
    public function download_to(string $url, string $destpath): void;

    /**
     * Check whether the Titus API is reachable.
     *
     * @return bool True if the API health endpoint responds successfully.
     */
    public function health(): bool;
}
