<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_tituscontentlibrary',
        get_string('pluginname', 'local_tituscontentlibrary')
    );
    $ADMIN->add('localplugins', $settings);
}
