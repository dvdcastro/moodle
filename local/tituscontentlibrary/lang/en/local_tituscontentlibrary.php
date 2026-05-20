<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']      = 'Titus Content Library';
$string['privacy:metadata'] = 'The Titus Content Library plugin does not store personal data. It stores course import metadata (course IDs, SCORM module IDs) that relate to Moodle courses, not to individual users.';
$string['manageintegration'] = 'Manage Titus integration';
$string['addcontent']      = 'Add content from Titus';
$string['catalogue']       = 'Titus Content Catalogue';
$string['addtomoodle']     = 'Add to Moodle';
$string['adding']          = 'Adding...';
$string['added']           = 'Added';
$string['failed']          = 'Failed';
$string['pending']         = 'Pending';

// Admin settings.
$string['settingsheading'] = 'Titus API configuration';
$string['apibaseurl'] = 'Titus API base URL';
$string['apibaseurl_desc'] = 'Base URL of the Titus Content Library API. For local development, use: http://localhost:8010/local/titusclsim/api';
$string['licencekey'] = 'Licence key';
$string['licencekey_desc'] = 'Your Titus Learning licence key. Stored encrypted using Moodle core encryption.';
$string['defaultcategoryid'] = 'Default course category';
$string['defaultcategoryid_desc'] = 'Category where courses imported from Titus will be created by default.';
$string['cachettl'] = 'Catalogue cache lifetime';
$string['cachettl_desc'] = 'How long to cache the catalogue before re-fetching from the Titus API.';
$string['scormrequired'] = 'Warning: The mod_scorm activity module is required but currently disabled. Enable it before using this plugin.';
