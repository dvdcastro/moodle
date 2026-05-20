<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']      = 'Titus Content Library';
$string['privacy:metadata:db:added'] = 'Records of Titus content items added to Moodle.';
$string['privacy:metadata:db:added:userid'] = 'The ID of the user who added the content.';
$string['privacy:metadata:db:added:contentid'] = 'The Titus content identifier.';
$string['privacy:metadata:db:added:courseid'] = 'The Moodle course created for the content.';
$string['privacy:metadata:db:added:timecreated'] = 'When the content was added.';
$string['privacy:metadata:external:titus'] = 'The Titus Learning API receives the licence key and content ID when downloading SCORM packages.';
$string['privacy:metadata:external:titus:licencekey'] = 'The licence key identifying the Moodle site.';
$string['privacy:metadata:external:titus:contentid'] = 'The content identifier being requested.';
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

// API error strings.
$string['error:apifailure']    = 'Titus API request failed: {$a}';
$string['error:apiauthfailed'] = 'Titus API authentication failed. Check your licence key.';
$string['error:apinotfound']   = 'Titus content not found: {$a}';
$string['error:apiratelimit']  = 'Titus API rate limit exceeded. Retry after {$a} seconds.';
$string['error:apiserver']     = 'Titus API server error: {$a}';
$string['error:apisecurity']   = 'Download URL security check failed: {$a}';
$string['error:apinetwork']    = 'Titus API network error: {$a}';
