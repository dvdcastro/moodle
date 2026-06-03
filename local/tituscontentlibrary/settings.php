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

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_tituscontentlibrary',
        get_string('pluginname', 'local_tituscontentlibrary')
    );
    $ADMIN->add('localplugins', $settings);

    // API Health indicator.
    $settings->add(new admin_setting_heading(
        'local_tituscontentlibrary/monitorheading',
        get_string('monitor:heading', 'local_tituscontentlibrary'),
        \local_tituscontentlibrary\local\monitoring::render_indicator()
    ));

    // Heading.
    $settings->add(new admin_setting_heading(
        'local_tituscontentlibrary/settingsheading',
        get_string('settingsheading', 'local_tituscontentlibrary'),
        ''
    ));

    // Warning if mod_scorm is disabled.
    $pluginman = core_plugin_manager::instance();
    $scorminfo = $pluginman->get_plugin_info('mod_scorm');
    if (!$scorminfo || $scorminfo->is_enabled() === false) {
        $settings->add(new admin_setting_heading(
            'local_tituscontentlibrary/scormwarning',
            '',
            html_writer::div(
                get_string('scormrequired', 'local_tituscontentlibrary'),
                'alert alert-danger'
            )
        ));
    }

    // 1. API base URL.
    $settings->add(new admin_setting_configtext(
        'local_tituscontentlibrary/apibaseurl',
        get_string('apibaseurl', 'local_tituscontentlibrary'),
        get_string('apibaseurl_desc', 'local_tituscontentlibrary'),
        'https://api.tituslearning.com/v1/',
        PARAM_URL
    ));

    // 2. Licence key (stored encrypted, required — must not be empty on first save).
    // The setting validates the key against the Titus API at write time and blocks
    // the save with an inline error if it is rejected (MLFR-225); no updatedcallback
    // is needed because validation happens inside write_setting().
    $licencekeysetting = new \local_tituscontentlibrary\admin\setting_encryptedpassword_required(
        'local_tituscontentlibrary/licencekey',
        get_string('licencekey', 'local_tituscontentlibrary'),
        get_string('licencekey_desc', 'local_tituscontentlibrary')
    );
    $settings->add($licencekeysetting);

    // 3. Default course category.
    $settings->add(new admin_settings_coursecat_select(
        'local_tituscontentlibrary/defaultcategoryid',
        get_string('defaultcategoryid', 'local_tituscontentlibrary'),
        get_string('defaultcategoryid_desc', 'local_tituscontentlibrary'),
        1
    ));

    // 4. Catalogue cache TTL.
    $settings->add(new admin_setting_configduration(
        'local_tituscontentlibrary/cachettl',
        get_string('cachettl', 'local_tituscontentlibrary'),
        get_string('cachettl_desc', 'local_tituscontentlibrary'),
        3600
    ));

    // 5. Tiles per page (marketplace pagination).
    $settings->add(new admin_setting_configselect(
        'local_tituscontentlibrary/tilesperpage',
        get_string('tilesperpage', 'local_tituscontentlibrary'),
        get_string('tilesperpage_desc', 'local_tituscontentlibrary'),
        '12',
        ['6' => '6', '12' => '12', '24' => '24', '48' => '48']
    ));

    // 6. Add mode: sync vs async course creation.
    $settings->add(new admin_setting_configselect(
        'local_tituscontentlibrary/addmode',
        get_string('addmode', 'local_tituscontentlibrary'),
        get_string('addmode_desc', 'local_tituscontentlibrary'),
        \local_tituscontentlibrary\config::ADDMODE_SYNC,
        [
            \local_tituscontentlibrary\config::ADDMODE_ASYNC => get_string('addmode:async', 'local_tituscontentlibrary'),
            \local_tituscontentlibrary\config::ADDMODE_SYNC  => get_string('addmode:sync',  'local_tituscontentlibrary'),
        ]
    ));

    // 7. Failure alert threshold.
    $settings->add(new admin_setting_configtext(
        'local_tituscontentlibrary/failurestreakthreshold',
        get_string('failurestreakthreshold', 'local_tituscontentlibrary'),
        get_string('failurestreakthreshold_desc', 'local_tituscontentlibrary'),
        '3',
        PARAM_INT
    ));
}
