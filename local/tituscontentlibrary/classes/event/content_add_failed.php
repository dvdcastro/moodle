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
 * Event fired after a Titus SCORM add fails.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - string $contentid Titus source content id.
 *      - string $errormessage Failure message.
 * }
 */
class content_add_failed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:contentaddfailed', 'local_tituscontentlibrary');
    }

    /**
     * Returns a human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        return "The attempt by user with id '{$this->userid}' to add Titus content " .
            "'{$this->other['contentid']}' failed: {$this->other['errormessage']}";
    }

    /**
     * Convenience factory to build and validate the event.
     *
     * @param string $contentid Titus source content id.
     * @param int $userid User who triggered the add.
     * @param string $errormessage Failure message.
     * @return self
     */
    public static function create_event(string $contentid, int $userid, string $errormessage): self {
        return self::create([
            'context' => \context_system::instance(),
            'userid'  => $userid,
            'other'   => [
                'contentid'    => $contentid,
                'errormessage' => $errormessage,
            ],
        ]);
    }

    /**
     * Validate the custom data.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['contentid'])) {
            throw new \coding_exception('The \'contentid\' value must be set in other.');
        }
        if (!isset($this->other['errormessage'])) {
            throw new \coding_exception('The \'errormessage\' value must be set in other.');
        }
    }
}
