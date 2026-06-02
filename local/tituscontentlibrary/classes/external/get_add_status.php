<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class get_add_status extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'contentid' => new external_value(PARAM_ALPHANUMEXT, 'Titus content ID'),
        ]);
    }

    public static function execute(string $contentid): array {
        global $DB;

        $params    = self::validate_parameters(self::execute_parameters(), ['contentid' => $contentid]);
        $contentid = $params['contentid'];

        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/tituscontentlibrary:viewlibrary', $ctx);

        $rec = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $contentid]);
        if (!$rec) {
            return ['status' => 'notqueued', 'course_url' => null, 'errormessage' => null];
        }

        $courseurl = null;
        if (!empty($rec->courseid)) {
            $courseurl = (new \moodle_url('/course/view.php', ['id' => (int)$rec->courseid]))->out(false);
        }

        $errormessage = null;
        if ($rec->status === 'failed' && !empty($rec->errormessage)) {
            $errormessage = \core_text::substr($rec->errormessage, 0, 500);
        }

        return [
            'status'       => $rec->status,
            'course_url'   => $courseurl,
            'errormessage' => $errormessage,
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'       => new external_value(PARAM_ALPHA, 'pending|processing|completed|failed|notqueued'),
            'course_url'   => new external_value(PARAM_URL, 'Course URL when completed', VALUE_OPTIONAL, null, NULL_ALLOWED),
            'errormessage' => new external_value(PARAM_TEXT, 'Error message when failed', VALUE_OPTIONAL, null, NULL_ALLOWED),
        ]);
    }
}
