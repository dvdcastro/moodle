<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname']      = 'Titus Content Library';
$string['privacy:metadata:db:added'] = 'Records of Titus content items added to Moodle.';
$string['privacy:metadata:db:added:addedby'] = 'The ID of the user who added the content.';
$string['privacy:metadata:db:added:contentid'] = 'The Titus content identifier.';
$string['privacy:metadata:db:added:courseid'] = 'The Moodle course created for the content.';
$string['privacy:metadata:db:added:status'] = 'The status of the content add operation (pending, processing, completed, failed).';
$string['privacy:metadata:db:added:errormessage'] = 'An error message recorded if the add operation failed.';
$string['privacy:metadata:db:added:timecreated'] = 'When the content was added.';
$string['privacy:metadata:external:titus'] = 'The Titus Learning API receives the licence key and content ID when downloading SCORM packages.';
$string['privacy:metadata:external:titus:licencekey'] = 'The licence key identifying the Moodle site.';
$string['privacy:metadata:external:titus:contentid'] = 'The content identifier being requested.';
// Capability strings (used by required_capability_exception).
$string['tituscontentlibrary:addcourse']         = 'Add content from Titus to Moodle';
$string['tituscontentlibrary:viewlibrary']       = 'View the Titus content catalogue';
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
$string['tilesperpage'] = 'Tiles per page';
$string['tilesperpage_desc'] = 'Number of content tiles displayed per page in the marketplace.';
$string['scormrequired'] = 'Warning: The mod_scorm activity module is required but currently disabled. Enable it before using this plugin.';

// API error strings.
$string['error:apifailure']    = 'Titus API request failed: {$a}';
$string['error:apiauthfailed'] = 'Titus API authentication failed. Check your licence key.';
$string['error:apiforbidden']  = 'Titus API access forbidden. The requested content may not be available in your subscription tier.';
$string['error:apinotfound']   = 'Titus content not found: {$a}';
$string['error:apiratelimit']  = 'Titus API rate limit exceeded. Retry after {$a} seconds.';
$string['error:apiserver']     = 'Titus API server error: {$a}';
$string['error:apisecurity']   = 'Download URL security check failed: {$a}';
$string['error:apinetwork']    = 'Titus API network error: {$a}';

// Scheduled task.
$string['task:refreshcatalogue']       = 'Refresh Titus catalogue cache';

// Catalogue availability strings.
$string['error:cataloguenotavailable'] = 'The Titus content catalogue is currently unavailable. Please try again later.';
$string['warning:cataloguestale']      = 'The catalogue shown may be outdated (last successful refresh: {$a}).';

// Pipeline / add_content_task strings.
$string['task:addcontent']               = 'Add Titus content to Moodle';
$string['error:modscormdisabled']        = 'The SCORM activity module is disabled. Enable mod_scorm before adding content.';
$string['error:invalidscormzip']         = 'The downloaded file is not a valid SCORM package: {$a}';
$string['error:contentidnotincatalogue'] = 'Content ID not found in catalogue: {$a}';
$string['emptycatalogue']              = 'No content available in the catalogue.';
$string['aria:addtomoodle']            = 'Add {$a} to Moodle';
$string['filterbycategory']            = 'Filter by category';
$string['allcategories']               = 'All';
$string['clearsearch']                 = 'Clear search';
$string['retry']                       = 'Retry';
$string['emptysearch']                 = 'No content found matching your search.';
$string['emptycategory']               = 'No content in this category.';
$string['showall']                     = 'Show all content';
$string['queued']                      = 'Queued';
$string['processing']                  = 'Processing';
$string['viewcourse']                  = 'View course';
$string['version']                     = 'Version';
$string['lastupdated']                 = 'Last updated';
$string['toast:added']                 = 'Course created successfully.';
$string['error:network']               = 'Connection lost. Click retry.';
$string['error:server']                = 'Server error. Try again in a few minutes.';
$string['error:timeout']               = 'Operation is taking longer than expected. Check back later.';
$string['announce:queueing']           = 'Queueing {$a}...';
$string['announce:queued']             = 'Waiting for processing slot for {$a}...';
$string['announce:processing']         = 'Importing {$a} into Moodle...';
$string['announce:completed']          = '{$a} added successfully.';
$string['announce:failed']             = '{$a} failed to import.';
$string['announce:results']            = '{$a} results.';

// Capability strings.
$string['tituscontentlibrary:manageplugin'] = 'Manage Titus content library';

// Manage page (MLFR-163).
$string['manage:pagetitle']         = 'Manage Titus Content Library';
$string['manage:heading']           = 'Manage Titus Content Library';
$string['manage:col:thumbnail']     = 'Thumbnail';
$string['manage:col:title']         = 'Title';
$string['manage:col:contentid']     = 'Content ID';
$string['manage:col:courseid']      = 'Moodle Course';
$string['manage:col:addedby']       = 'Added by';
$string['manage:col:timecreated']   = 'Date added';
$string['manage:col:status']        = 'Status';
$string['manage:col:actions']       = 'Actions';
$string['manage:status:ok']          = 'OK';
$string['manage:status:coursedeleted']= 'Course deleted';
$string['manage:status:outofdate']   = 'Out of date';
$string['manage:status:failed']      = 'Failed';
$string['manage:status:processing']  = 'Processing';
$string['manage:status:pending']     = 'Pending';
$string['manage:action:viewcourse']  = 'View course';
$string['manage:action:resync']      = 'Re-sync';
$string['manage:action:remove']      = 'Remove';
$string['manage:confirm:remove']     = 'Are you sure you want to remove this tracking record? The Moodle course will NOT be deleted.';
$string['manage:confirm:resync']     = 'This will re-download the SCORM package and replace it in the existing course. Enrolments and grades are preserved. Continue?';
$string['manage:remove:success']     = 'Tracking record removed.';
$string['manage:resync:queued']      = 'Re-sync queued successfully.';

// Re-sync task (MLFR-164).
$string['task:resynccontent']                   = 'Re-sync Titus SCORM content';
$string['error:cannotresync_notcompleted']      = 'Cannot re-sync: content import has not completed yet.';
$string['error:cannotresync_courseorcmmissing'] = 'Cannot re-sync: course or SCORM activity reference is missing.';

// Detail modal (MLFR-165).
$string['detail:viewdetails']  = 'View details';
$string['detail:close']        = 'Close';

// Sort options (MLFR-162).
$string['sortby']          = 'Sort by';
$string['sort:az']         = 'Title A–Z';
$string['sort:za']         = 'Title Z–A';
$string['sort:newest']     = 'Newest first';
$string['sort:duration']   = 'Duration';
$string['sort:category']   = 'Category';
$string['sort:featured']   = 'Featured first';
$string['sort:new']        = 'New first';

// Content badges (MLFR-166).
$string['badge:new']       = 'New';
$string['badge:featured']  = 'Featured';
$string['duration:minutes'] = 'min';

// Monitoring / API health (MLFR-173).
$string['monitor:heading']        = 'API Health';
$string['monitor:ok']             = 'OK — catalogue refreshing normally';
$string['monitor:warning']        = 'Warning — {$a} consecutive refresh failures';
$string['monitor:critical']       = 'Critical — {$a} consecutive refresh failures';
$string['monitor:notify:subject'] = 'Titus Content Library: catalogue refresh failures';
$string['monitor:notify:body']    = 'The Titus Content Library catalogue has failed to refresh {$a} times consecutively. Please check the API configuration in Site Administration.';
$string['failurestreakthreshold']      = 'Failure alert threshold';
$string['failurestreakthreshold_desc'] = 'Send an admin notification after this many consecutive catalogue refresh failures (default: 3).';
$string['messageprovider:refreshfailure'] = 'Titus catalogue refresh failure notifications';

// Settings connection test feedback (lib.php validate_connexion callback).
$string['connexion:ok']          = 'Titus API connection verified successfully.';
$string['connexion:invalid_key'] = 'Titus API returned 401/403 — check your licence key.';
$string['connexion:unreachable'] = 'Could not verify the Titus API connection (network error or unreachable). The licence key was saved.';
$string['licencekey:required']   = 'A Titus licence key is required.';

// Add mode setting (MLFR-247).
$string['addmode']       = 'Add mode';
$string['addmode_desc']  = 'Synchronous mode (default) blocks the browser request until the course is created and returns the course link immediately — matching the brief and suitable for small/fast packages. Asynchronous mode queues a background task and the tile polls for completion; it is safer for very large packages and avoids web-server timeout risks.';
$string['addmode:async'] = 'Asynchronous (background task)';
$string['addmode:sync']  = 'Synchronous (blocks until done, returns course link — default)';

// Events (MLFR-220).
$string['event:contentadded']           = 'Titus content added';
$string['event:contentaddfailed']       = 'Titus content add failed';
$string['event:cataloguerefreshed']     = 'Titus catalogue refreshed';
$string['event:cataloguerefreshfailed'] = 'Titus catalogue refresh failed';
$string['event:courseresynced']         = 'Titus course resynced';
