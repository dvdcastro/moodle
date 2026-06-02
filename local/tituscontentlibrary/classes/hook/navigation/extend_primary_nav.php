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

namespace local_tituscontentlibrary\hook\navigation;

defined('MOODLE_INTERNAL') || die();

/**
 * Callback that injects the Titus marketplace link into the primary navigation.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class extend_primary_nav {

    /**
     * Add the Titus Content Library link to the primary navigation for users
     * who have the :view capability.
     *
     * @param \core\hook\navigation\primary_extend $hook
     */
    public static function callback(\core\hook\navigation\primary_extend $hook): void {
        if (!isloggedin() || isguestuser()) {
            return;
        }
        $ctx = \context_system::instance();
        if (!has_capability('local/tituscontentlibrary:viewlibrary', $ctx)) {
            return;
        }
        $primaryview = $hook->get_primaryview();
        $primaryview->add(
            get_string('pluginname', 'local_tituscontentlibrary'),
            new \moodle_url('/local/tituscontentlibrary/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'tituscontentlibrary',
            new \pix_icon('i/course', '')
        );
    }
}
