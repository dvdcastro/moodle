<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/tituscontentlibrary/index.php'));
// MLFR-268: use the standard pagelayout, not 'admin' — this is a user-facing
// marketplace browsed by managers/teachers, not a site-administration screen.
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('catalogue', 'local_tituscontentlibrary'));
$PAGE->set_heading(get_string('catalogue', 'local_tituscontentlibrary'));

require_capability('local/tituscontentlibrary:viewlibrary', $context);

echo $OUTPUT->header();

if (has_capability('local/tituscontentlibrary:manageplugin', $context)) {
    echo html_writer::link(
        new moodle_url('/local/tituscontentlibrary/manage.php'),
        get_string('manage:pagetitle', 'local_tituscontentlibrary'),
        ['class' => 'btn btn-secondary btn-sm float-right mb-2']
    );
}

if (!\local_tituscontentlibrary\local\dependencies::is_mod_scorm_enabled()) {
    echo $OUTPUT->notification(get_string('scormrequired', 'local_tituscontentlibrary'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$cm         = new \local_tituscontentlibrary\local\catalogue_manager();
$contents   = $cm->get_catalogue();
$categories = $cm->get_categories();
$errorstate = empty($contents) && !$cm->is_stale();

$page     = new \local_tituscontentlibrary\output\marketplace_page($contents, $categories, $cm->is_stale(), $errorstate);
$renderer = $PAGE->get_renderer('local_tituscontentlibrary');

echo $renderer->render_marketplace_page($page);

echo $OUTPUT->footer();
