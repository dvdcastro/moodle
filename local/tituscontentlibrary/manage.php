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

require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/tituscontentlibrary/manage.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage:pagetitle', 'local_tituscontentlibrary'));
$PAGE->set_heading(get_string('manage:heading', 'local_tituscontentlibrary'));

require_capability('local/tituscontentlibrary:manageplugin', $context);

$action = optional_param('action', '', PARAM_ALPHA);
$rowid  = optional_param('id', 0, PARAM_INT);

if ($action === 'remove' && $rowid > 0) {
    if (!optional_param('confirmed', 0, PARAM_BOOL)) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(
            get_string('manage:confirm:remove', 'local_tituscontentlibrary'),
            new moodle_url($PAGE->url, ['action' => 'remove', 'id' => $rowid, 'confirmed' => 1, 'sesskey' => sesskey()]),
            $PAGE->url
        );
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    $cm = new \local_tituscontentlibrary\local\catalogue_manager();
    $cm->remove_added_row($rowid);
    redirect(
        $PAGE->url,
        get_string('manage:remove:success', 'local_tituscontentlibrary'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$PAGE->requires->js_call_amd('local_tituscontentlibrary/manage', 'init', ['[data-region="titus-manage"]']);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage:heading', 'local_tituscontentlibrary'));

echo html_writer::link(
    new moodle_url('/local/tituscontentlibrary/index.php'),
    get_string('catalogue', 'local_tituscontentlibrary'),
    ['class' => 'btn btn-secondary mb-3 mr-2']
);

echo html_writer::start_div('', ['data-region' => 'titus-manage']);

$table = new \local_tituscontentlibrary\output\manage_table('titus-manage-table');
$table->define_baseurl($PAGE->url);
$table->out(25, true);

echo html_writer::end_div();

echo $OUTPUT->footer();
