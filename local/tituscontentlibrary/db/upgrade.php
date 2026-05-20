<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_tituscontentlibrary_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026010103) {
        // Create local_tituscontentlibrary_added table.
        $table = new xmldb_table('local_tituscontentlibrary_added');

        $table->add_field('id',           XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('contentid',    XMLDB_TYPE_CHAR,    '64', null, XMLDB_NOTNULL);
        $table->add_field('courseid',     XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('scormcmid',    XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('userid',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('status',       XMLDB_TYPE_CHAR,    '20', null, XMLDB_NOTNULL, null, 'completed');
        $table->add_field('errormessage', XMLDB_TYPE_TEXT,    null, null, null);
        $table->add_field('timecreated',  XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary',        XMLDB_KEY_PRIMARY,  ['id']);
        $table->add_key('contentid_uniq', XMLDB_KEY_UNIQUE,   ['contentid']);
        $table->add_key('fk_userid',      XMLDB_KEY_FOREIGN,  ['userid'],   'user',   ['id']);
        $table->add_key('fk_courseid',    XMLDB_KEY_FOREIGN,  ['courseid'], 'course', ['id']);

        $table->add_index('status_ix',   XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026010103, 'local', 'tituscontentlibrary');
    }

    return true;
}
