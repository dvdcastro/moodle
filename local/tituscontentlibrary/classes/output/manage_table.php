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

namespace local_tituscontentlibrary\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use local_tituscontentlibrary\local\catalogue_manager;
use local_tituscontentlibrary\local\manage_row_status;

/**
 * Admin table showing all content items that have been added to Moodle.
 *
 * Displays per-row status including orphan detection (course deleted or no
 * longer in the Titus catalogue).
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manage_table extends \table_sql {

    /** @var array<int,bool> Lazy cache of course existence keyed by courseid. */
    private array $course_exists_cache = [];

    /** @var array<string,true> Set of content IDs currently in the Titus catalogue. */
    private array $catalogue_ids = [];

    /** @var array<string,string> Map contentid → thumbnailurl from catalogue. */
    private array $thumbnail_map = [];

    /** @var array<string,string> Map contentid → title from catalogue. */
    private array $title_map = [];

    public function __construct(string $uniqueid) {
        parent::__construct($uniqueid);

        // Pre-load catalogue for orphan/out-of-date detection. If the API is
        // unreachable or the cache is cold, fall back to an empty map so the
        // page still renders the previously-added rows (admin can still remove
        // or view associated courses).
        try {
            $cm    = new catalogue_manager();
            $items = $cm->get_catalogue();
            foreach ($items as $item) {
                $this->catalogue_ids[$item->id] = true;
                if (!empty($item->thumbnail_url)) {
                    $this->thumbnail_map[$item->id] = $item->thumbnail_url;
                }
                if (!empty($item->title)) {
                    $this->title_map[$item->id] = $item->title;
                }
            }
        } catch (\Throwable $e) {
            debugging('local_tituscontentlibrary manage_table: catalogue pre-load failed: '
                . $e->getMessage(), DEBUG_DEVELOPER);
            // Degrade gracefully — orphan detection will mark all rows as such,
            // but the table itself remains usable for Remove/View Course actions.
        }

        $this->define_columns([
            'thumbnail', 'title', 'contentid', 'courseid',
            'addedby', 'timecreated', 'status', 'actions',
        ]);
        $this->define_headers([
            get_string('manage:col:thumbnail',  'local_tituscontentlibrary'),
            get_string('manage:col:title',      'local_tituscontentlibrary'),
            get_string('manage:col:contentid',  'local_tituscontentlibrary'),
            get_string('manage:col:courseid',   'local_tituscontentlibrary'),
            get_string('manage:col:addedby',    'local_tituscontentlibrary'),
            get_string('manage:col:timecreated','local_tituscontentlibrary'),
            get_string('manage:col:status',     'local_tituscontentlibrary'),
            get_string('manage:col:actions',    'local_tituscontentlibrary'),
        ]);

        $this->no_sorting('thumbnail');
        $this->no_sorting('addedby');
        $this->no_sorting('status');
        $this->no_sorting('actions');
        $this->sortable(true, 'timecreated', SORT_DESC);

        // Pull the full set of user name fields so fullname() does not emit
        // E_USER_NOTICE about missing fields (firstnamephonetic, etc).
        $namefieldssql = implode(', ', array_map(
            static fn($f) => "u.{$f}",
            \core_user\fields::get_name_fields()
        ));

        $this->set_sql(
            'a.id, a.contentid, a.courseid, a.cmid, a.scormid, a.addedby, a.status,
             a.errormessage, a.timecreated, a.timemodified, ' . $namefieldssql,
            '{local_tituscontentlibrary_added} a LEFT JOIN {user} u ON u.id = a.addedby',
            '1=1',
            []
        );
    }

    public function col_thumbnail(\stdClass $row): string {
        $url = $this->thumbnail_map[$row->contentid] ?? '';
        if (!$url) {
            return '';
        }
        return \html_writer::empty_tag('img', [
            'src'     => \clean_param($url, PARAM_URL),
            'alt'     => '',
            'class'   => 'titus-manage-thumb',
            'loading' => 'lazy',
        ]);
    }

    public function col_title(\stdClass $row): string {
        // Resolve the human-readable title from the catalogue map built at load time
        // (MLFR-282). Fall back to the content ID when the item is no longer in the
        // catalogue (orphaned) or the catalogue could not be loaded.
        $title = $this->title_map[$row->contentid] ?? $row->contentid;
        return \html_writer::span(\s($title));
    }

    public function col_contentid(\stdClass $row): string {
        return \html_writer::tag('code', \s($row->contentid));
    }

    public function col_courseid(\stdClass $row): string {
        if (empty($row->courseid)) {
            return \html_writer::span(
                get_string('manage:status:coursedeleted', 'local_tituscontentlibrary'),
                'badge badge-danger'
            );
        }
        if (!$this->course_exists((int)$row->courseid)) {
            return \html_writer::span(
                get_string('manage:status:coursedeleted', 'local_tituscontentlibrary'),
                'badge badge-danger'
            );
        }
        $url = new \moodle_url('/course/view.php', ['id' => $row->courseid]);
        return \html_writer::link($url, (string)$row->courseid);
    }

    public function col_addedby(\stdClass $row): string {
        if (empty($row->addedby)) {
            return '';
        }
        return \html_writer::link(
            new \moodle_url('/user/view.php', ['id' => $row->addedby]),
            \fullname($row)
        );
    }

    public function col_timecreated(\stdClass $row): string {
        return \userdate($row->timecreated);
    }

    public function col_status(\stdClass $row): string {
        $courseexists   = !empty($row->courseid) && $this->course_exists((int)$row->courseid);
        $catalogueids   = array_keys($this->catalogue_ids);
        $displaystatus  = manage_row_status::derive($row, $courseexists, $catalogueids);

        $badgeclass = match ($displaystatus) {
            manage_row_status::OK             => 'badge-success',
            manage_row_status::FAILED         => 'badge-danger',
            manage_row_status::COURSE_DELETED => 'badge-danger',
            manage_row_status::OUT_OF_DATE    => 'badge-warning',
            manage_row_status::PROCESSING,
            manage_row_status::PENDING        => 'badge-info',
            default                           => 'badge-secondary',
        };

        $label = get_string('manage:status:' . $displaystatus, 'local_tituscontentlibrary');
        return \html_writer::span($label, 'badge ' . $badgeclass);
    }

    public function col_actions(\stdClass $row): string {
        global $PAGE;
        $actions = [];

        $courseexists = !empty($row->courseid) && $this->course_exists((int)$row->courseid);
        if ($courseexists) {
            $actions[] = \html_writer::link(
                new \moodle_url('/course/view.php', ['id' => $row->courseid]),
                get_string('manage:action:viewcourse', 'local_tituscontentlibrary'),
                ['class' => 'btn btn-sm btn-outline-secondary mr-1']
            );
        }

        if ($row->status === \local_tituscontentlibrary\local\content_status::COMPLETED && $courseexists) {
            $actions[] = \html_writer::tag('button',
                get_string('manage:action:resync', 'local_tituscontentlibrary'),
                [
                    'type'             => 'button',
                    'class'            => 'btn btn-sm btn-outline-primary mr-1',
                    'data-action'      => 'resync',
                    'data-content-id'  => \s($row->contentid),
                ]
            );
        }

        $removeurl = new \moodle_url($PAGE->url, ['action' => 'remove', 'id' => $row->id]);
        $actions[] = \html_writer::link(
            $removeurl,
            get_string('manage:action:remove', 'local_tituscontentlibrary'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );

        return implode(' ', $actions);
    }

    /**
     * Cached course existence check.
     *
     * @param int $courseid
     * @return bool
     */
    private function course_exists(int $courseid): bool {
        if (!array_key_exists($courseid, $this->course_exists_cache)) {
            global $DB;
            $this->course_exists_cache[$courseid] = $DB->record_exists('course', ['id' => $courseid]);
        }
        return $this->course_exists_cache[$courseid];
    }
}
