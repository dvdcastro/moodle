<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

class dependencies {

    public static function is_mod_scorm_enabled(): bool {
        $pluginman = \core_plugin_manager::instance();
        $info      = $pluginman->get_plugin_info('mod_scorm');
        return $info !== null && $info->is_enabled() !== false;
    }
}
