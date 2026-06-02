<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_tituscontentlibrary\local\catalogue_manager;
use local_tituscontentlibrary\local\catalogue_mapper;

class get_catalogue extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([]);
    }

    public static function execute(): array {
        $ctx = \context_system::instance();
        self::validate_context($ctx);
        require_capability('local/tituscontentlibrary:viewlibrary', $ctx);

        $cm    = new catalogue_manager();
        $items = $cm->get_catalogue();
        $stale = $cm->is_stale();

        $result = [];
        foreach ($items as $dto) {
            $result[] = catalogue_mapper::dto_to_array($dto);
        }

        return ['items' => $result, 'stale' => $stale];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'id'               => new external_value(PARAM_ALPHANUMEXT, 'Content ID'),
                    'title'            => new external_value(PARAM_TEXT, 'Title'),
                    'description'      => new external_value(PARAM_TEXT, 'Long description'),
                    'shortdescription' => new external_value(PARAM_TEXT, 'Short description'),
                    'thumbnailurl'     => new external_value(PARAM_URL, 'Thumbnail URL'),
                    'category'         => new external_value(PARAM_TEXT, 'Category'),
                    'tags'             => new external_multiple_structure(
                        new external_value(PARAM_TEXT, 'Tag')
                    ),
                    'durationminutes'  => new external_value(PARAM_INT, 'Duration in minutes'),
                    'isfeatured'       => new external_value(PARAM_BOOL, 'Whether item is featured'),
                    'isnew'            => new external_value(PARAM_BOOL, 'Whether item is new'),
                    'publishedat'      => new external_value(PARAM_INT, 'Unix timestamp of publication', VALUE_OPTIONAL, null, NULL_ALLOWED),
                ])
            ),
            'stale' => new external_value(PARAM_BOOL, 'True if catalogue data may be outdated'),
        ]);
    }
}
