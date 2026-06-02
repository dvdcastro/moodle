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

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\config;

/**
 * Catalogue manager: fetches, caches, and exposes the Titus content catalogue.
 *
 * Implements a degraded mode: if the API is unavailable, the last successfully
 * cached catalogue is returned and marked as stale. If there is no cached data
 * at all, an empty array is returned.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class catalogue_manager {

    /** @var titus_api_client_interface */
    private titus_api_client_interface $client;

    /**
     * Constructor.
     *
     * @param titus_api_client_interface|null $client Optional API client for testing/DI.
     */
    public function __construct(?titus_api_client_interface $client = null) {
        $this->client = $client ?? client_factory::get_client();
    }

    /**
     * Return the full content catalogue.
     *
     * Reads from cache unless the cache is expired or $forcerefresh is true.
     * On API failure, falls back to stale cached data (degraded mode).
     *
     * @param bool $forcerefresh When true, bypass the cache and re-fetch from the API.
     * @return array Array of content_dto objects (or empty array in degraded mode with no cache).
     */
    public function get_catalogue(bool $forcerefresh = false): array {
        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $ttl   = config::get_cache_ttl();

        $meta    = $cache->get('meta') ?: ['fetched_at' => 0, 'stale' => false];
        $expired = (time() - $meta['fetched_at']) > $ttl;

        if (!$forcerefresh && !$expired) {
            $data = $cache->get('all');
            if ($data !== false) {
                return $data;
            }
        }

        try {
            $items = $this->client->get_catalogue();
            $cache->set('all', $items);
            $cache->set('meta', ['fetched_at' => time(), 'stale' => false]);
            set_config('last_successful_refresh', time(), 'local_tituscontentlibrary');
            monitoring::record_success();
            // Fire the catalogue_refreshed event (MLFR-220).
            \local_tituscontentlibrary\event\catalogue_refreshed::create_event(count($items))->trigger();
            return $items;
        } catch (\Throwable $e) {
            debugging('[titus_catalogue] API error: ' . $e->getMessage(), DEBUG_NORMAL);
            monitoring::record_failure();
            // Fire the catalogue_refresh_failed event (MLFR-220).
            \local_tituscontentlibrary\event\catalogue_refresh_failed::create_event($e->getMessage())->trigger();
            $stale = $cache->get('all');
            if ($stale !== false) {
                $cache->set('meta', array_merge($meta, ['stale' => true]));
                return $stale;
            }
            return [];
        }
    }

    /**
     * Return a sorted, unique list of category names from the catalogue.
     *
     * @return string[] Sorted category names.
     */
    public function get_categories(): array {
        $items      = $this->get_catalogue();
        $categories = [];
        foreach ($items as $item) {
            if (!empty($item->category)) {
                $categories[] = $item->category;
            }
        }
        $categories = array_unique($categories);
        usort($categories, 'strcasecmp');
        return array_values($categories);
    }

    /**
     * Remove an added-content tracking row by its ID.
     *
     * This ONLY removes the tracking record — it does NOT delete the Moodle course
     * or the SCORM activity. Used by the manage page "Remove" action.
     *
     * @param int $rowid Primary key of local_tituscontentlibrary_added.
     */
    public function remove_added_row(int $rowid): void {
        global $DB;
        $DB->delete_records('local_tituscontentlibrary_added', ['id' => $rowid]);
    }

    /**
     * Invalidate the catalogue cache (both data and metadata).
     *
     * @return void
     */
    public function invalidate_cache(): void {
        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $cache->delete('all');
        $cache->delete('meta');
    }

    /**
     * Return whether the currently cached catalogue is stale (i.e. an API failure
     * occurred and the data is from a previous successful fetch).
     *
     * @return bool True if the catalogue is stale.
     */
    public function is_stale(): bool {
        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $meta  = $cache->get('meta');
        return $meta !== false && !empty($meta['stale']);
    }
}
