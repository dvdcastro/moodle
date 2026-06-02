<?php
namespace local_tituscontentlibrary\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_tituscontentlibrary_added',
            [
                'addedby'      => 'privacy:metadata:db:added:addedby',
                'contentid'    => 'privacy:metadata:db:added:contentid',
                'courseid'     => 'privacy:metadata:db:added:courseid',
                'status'       => 'privacy:metadata:db:added:status',
                'errormessage' => 'privacy:metadata:db:added:errormessage',
                'timecreated'  => 'privacy:metadata:db:added:timecreated',
            ],
            'privacy:metadata:db:added'
        );
        $collection->add_external_location_link(
            'titus_api',
            [
                'licencekey' => 'privacy:metadata:external:titus:licencekey',
                'contentid'  => 'privacy:metadata:external:titus:contentid',
            ],
            'privacy:metadata:external:titus'
        );
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_tituscontentlibrary_added} a ON a.addedby = :userid
                 WHERE ctx.contextlevel = :contextlevel";
        $contextlist->add_from_sql($sql, [
            'userid'       => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $sql = "SELECT DISTINCT addedby FROM {local_tituscontentlibrary_added}";
        $userlist->add_from_sql('addedby', $sql, []);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_SYSTEM) {
                continue;
            }
            $userid = $contextlist->get_user()->id;
            $records = $DB->get_records('local_tituscontentlibrary_added', ['addedby' => $userid]);
            $data = array_values(array_map(function($r) {
                return (object)[
                    'contentid'   => $r->contentid,
                    'courseid'    => $r->courseid,
                    'status'      => $r->status,
                    'timecreated' => \core_privacy\local\request\transform::datetime($r->timecreated),
                ];
            }, $records));
            writer::with_context($context)->export_data(['Titus Content Library'], (object)['imports' => $data]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('local_tituscontentlibrary_added');
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_SYSTEM) {
                $DB->delete_records('local_tituscontentlibrary_added', ['addedby' => $contextlist->get_user()->id]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_tituscontentlibrary_added', "addedby $insql", $inparams);
    }
}
