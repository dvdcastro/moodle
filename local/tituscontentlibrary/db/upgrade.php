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

    if ($oldversion < 2026052200) {
        // No schema change — version bump registers the new :manage capability.
        upgrade_plugin_savepoint(true, 2026052200, 'local', 'tituscontentlibrary');
    }

    if ($oldversion < 2026052300) {
        // No schema change — version bump registers the refreshfailure message provider.
        upgrade_plugin_savepoint(true, 2026052300, 'local', 'tituscontentlibrary');
    }

    if ($oldversion < 2026052400) {
        // No schema change — v2.0.0 release (a11y pass, DEVELOPER.md).
        upgrade_plugin_savepoint(true, 2026052400, 'local', 'tituscontentlibrary');
    }

    if ($oldversion < 2026060202) {
        // Wave D schema rename reconciliation (MLFR-255).
        //
        // Sites that installed at 2026010103 received the original field names
        // (scormcmid, userid) and never got an upgrade step to the renamed schema
        // (cmid, addedby) or the added scormid field that install.xml now declares.
        // Bring those sites in line with install.xml here. Guarded with field_exists
        // so re-runs and fresh installs (which already match install.xml) are no-ops.
        $table = new xmldb_table('local_tituscontentlibrary_added');

        // scormcmid -> cmid.
        $oldcmid = new xmldb_field('scormcmid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        if ($dbman->field_exists($table, $oldcmid)) {
            $dbman->rename_field($table, $oldcmid, 'cmid');
        }

        // userid -> addedby. Drop the dependent FK first, rename, then re-add it.
        $olduserid = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $olduserid)) {
            $oldfk = new xmldb_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $dbman->drop_key($table, $oldfk);
            $dbman->rename_field($table, $olduserid, 'addedby');
            $newfk = new xmldb_key('fk_addedby', XMLDB_KEY_FOREIGN, ['addedby'], 'user', ['id']);
            $dbman->add_key($table, $newfk);
        }

        // Add scormid if it is missing (was not in the original schema).
        $scormid = new xmldb_field('scormid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'cmid');
        if (!$dbman->field_exists($table, $scormid)) {
            $dbman->add_field($table, $scormid);
        }

        // Add courseid_ix index if missing (install.xml declares it; original upgrade did not).
        $courseidix = new xmldb_index('courseid_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->index_exists($table, $courseidix)) {
            $dbman->add_index($table, $courseidix);
        }

        upgrade_plugin_savepoint(true, 2026060202, 'local', 'tituscontentlibrary');
    }

    return true;
}
