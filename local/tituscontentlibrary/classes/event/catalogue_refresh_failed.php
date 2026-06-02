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

namespace local_tituscontentlibrary\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event fired after a Titus catalogue refresh fails.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string $errormessage Failure message.
 * }
 */
class catalogue_refresh_failed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:cataloguerefreshfailed', 'local_tituscontentlibrary');
    }

    /**
     * Returns a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The Titus catalogue refresh failed: {$this->other['errormessage']}";
    }

    /**
     * Convenience factory to build and validate the event.
     *
     * @param string $errormessage Failure message.
     * @return self
     */
    public static function create_event(string $errormessage): self {
        return self::create([
            'context' => \context_system::instance(),
            'other'   => ['errormessage' => $errormessage],
        ]);
    }

    /**
     * Validate the custom data.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['errormessage'])) {
            throw new \coding_exception('The \'errormessage\' value must be set in other.');
        }
    }
}
