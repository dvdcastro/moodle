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

namespace local_tituscontentlibrary\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: refresh the Titus content catalogue cache.
 *
 * Runs hourly by default. Forces a fresh fetch from the API and stores
 * the result in the MUC 'catalogue' cache.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class refresh_catalogue_task extends \core\task\scheduled_task {

    /**
     * Return the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:refreshcatalogue', 'local_tituscontentlibrary');
    }

    /**
     * Execute the task: force-refresh the catalogue cache.
     *
     * @return void
     * @throws \Throwable Re-throws any exception so Moodle can record task failure.
     */
    public function execute(): void {
        $manager = new \local_tituscontentlibrary\local\catalogue_manager();
        try {
            $items = $manager->get_catalogue(true);
            mtrace('[titus] catalogue refreshed: ' . count($items) . ' items');
        } catch (\Throwable $e) {
            mtrace('[titus] catalogue refresh FAILED: ' . $e->getMessage());
            throw $e;
        }
    }
}
