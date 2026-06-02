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

/**
 * Behat step definitions for local_tituscontentlibrary.
 *
 * @package   local_tituscontentlibrary
 * @category  test
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL check — Behat step files are loaded directly by the Behat CLI.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Context;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\tests\fixtures\mock_titus_api_client;

/**
 * Custom Behat step definitions for local_tituscontentlibrary.
 */
class behat_local_tituscontentlibrary extends behat_base {

    /**
     * Build the standard set of simulated catalogue content items.
     *
     * @return content_dto[]
     */
    private function build_catalogue_items(): array {
        return [
            new content_dto(
                id: 'titus-001',
                title: 'Leadership Foundations',
                description: 'Develop essential leadership skills.',
                short_description: 'Build the core skills needed to lead teams.',
                thumbnail_url: '',
                category: 'Leadership',
                tags: ['leadership', 'management'],
                duration_minutes: 60,
                is_featured: true,
                is_new: false,
            ),
            new content_dto(
                id: 'titus-002',
                title: 'Advanced Leadership',
                description: 'Take your leadership to the next level.',
                short_description: 'Advanced strategies for experienced leaders.',
                thumbnail_url: '',
                category: 'Leadership',
                tags: ['leadership', 'strategy'],
                duration_minutes: 90,
                is_featured: false,
                is_new: true,
            ),
            new content_dto(
                id: 'titus-003',
                title: 'Leadership in Crisis',
                description: 'Lead effectively during crises.',
                short_description: 'Lead with clarity and calm when your organisation needs it.',
                thumbnail_url: '',
                category: 'Leadership',
                tags: ['leadership', 'crisis', 'resilience'],
                duration_minutes: 45,
                is_featured: false,
                is_new: false,
            ),
            new content_dto(
                id: 'titus-004',
                title: 'Python for Beginners',
                description: 'Start your programming journey with Python.',
                short_description: 'A practical introduction to Python programming.',
                thumbnail_url: '',
                category: 'Technology',
                tags: ['python', 'programming'],
                duration_minutes: 120,
                is_featured: true,
                is_new: false,
            ),
        ];
    }

    /**
     * Prime the MUC catalogue cache with a set of simulated content items.
     * This avoids any dependency on a live API endpoint during Behat tests.
     *
     * @Given /^the Titus catalogue cache is primed with content items$/
     */
    public function the_titus_catalogue_cache_is_primed_with_content_items(): void {
        $items = $this->build_catalogue_items();

        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $cache->set('all', $items);
        $cache->set('meta', ['fetched_at' => time(), 'stale' => false]);

        // Configure plugin settings so page renders without errors.
        set_config('apibaseurl', 'http://localhost/local/titusclsim/api', 'local_tituscontentlibrary');
        set_config('defaultcategoryid', 1, 'local_tituscontentlibrary');
    }

    /**
     * Prime the catalogue AND inject a mock API client backed by a real SCORM
     * ZIP fixture, so that running the add_content_task adhoc task in-process
     * (via "I run the adhoc task ...") creates a genuine course + SCORM activity
     * without any live API dependency.
     *
     * IMPORTANT — process model: the "Add" button calls the add_course web
     * service in the *web server* process, which only queues the adhoc task (no
     * client needed). The task itself is executed later in the *Behat CLI*
     * process by the "I run the adhoc task" step, which is where this injected
     * client (a static override on client_factory) takes effect. This is why
     * draining the queue end-to-end works in Behat for this plugin.
     *
     * @Given /^the Titus catalogue is primed with a downloadable SCORM package$/
     */
    public function the_titus_catalogue_is_primed_with_downloadable_scorm(): void {
        global $CFG;

        $items = $this->build_catalogue_items();

        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $cache->set('all', $items);
        $cache->set('meta', ['fetched_at' => time(), 'stale' => false]);

        set_config('apibaseurl', 'http://localhost/local/titusclsim/api', 'local_tituscontentlibrary');
        set_config('defaultcategoryid', 1, 'local_tituscontentlibrary');

        // Load the mock client fixture (not autoloaded).
        require_once($CFG->dirroot . '/local/tituscontentlibrary/tests/fixtures/mock_titus_api_client.php');

        // Use Moodle's own valid SCORM 1.2 package as the downloaded ZIP.
        $scormzip = $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip';

        $mock = (new mock_titus_api_client())
            ->with_catalogue($items)
            ->with_zip_fixture($scormzip);

        client_factory::set_test_client($mock);
    }

    /**
     * Grant the given user the local/tituscontentlibrary:viewlibrary capability.
     *
     * @Given /^"(?P<username>[^"]+)" has the Titus view capability$/
     */
    public function user_has_the_titus_view_capability(string $username): void {
        global $DB;

        $user    = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
        $context = \context_system::instance();
        $role    = $DB->get_record('role', ['shortname' => 'manager']);

        if (!$role) {
            throw new \coding_exception('Manager role not found');
        }

        if (!$DB->record_exists('role_assignments', [
            'userid'    => $user->id,
            'roleid'    => $role->id,
            'contextid' => $context->id,
        ])) {
            role_assign($role->id, $user->id, $context->id);
        }
    }

    /**
     * Wait until a tile with the given data-content-id has a text matching the pattern.
     * Used to verify async tile state changes.
     *
     * @Then /^I wait until the tile "(?P<contentid>[^"]+)" contains "(?P<text>[^"]+)"$/
     */
    public function i_wait_until_tile_contains(string $contentid, string $text): void {
        $this->spin(function() use ($contentid, $text) {
            $node = $this->getSession()->getPage()->find(
                'css',
                '[data-content-id="' . $contentid . '"]'
            );
            if (!$node || strpos($node->getText(), $text) === false) {
                throw new \Exception(
                    'Tile "' . $contentid . '" does not contain "' . $text . '"'
                );
            }
            return true;
        }, false, 30);
    }

    /**
     * Run the next pending adhoc task for a given class.
     *
     * @When /^I run the adhoc task "(?P<classname>[^"]+)"$/
     */
    public function i_run_the_adhoc_task(string $classname): void {
        $task = \core\task\manager::get_next_adhoc_task(time(), false, $classname);
        if (!$task) {
            throw new \Exception("No pending adhoc task found for class: {$classname}");
        }
        try {
            $task->execute();
            \core\task\manager::adhoc_task_complete($task);
        } catch (\Throwable $e) {
            \core\task\manager::adhoc_task_failed($task);
            throw $e;
        }
    }

    /**
     * Assert that the given Titus content item was imported as a Moodle course
     * containing a SCORM activity. Verifies the persisted _added row and the
     * presence of an actual scorm instance + course module in the new course.
     *
     * @Then /^the Titus content "(?P<contentid>[^"]+)" should have created a course with a SCORM activity$/
     */
    public function content_should_have_created_course_with_scorm(string $contentid): void {
        global $DB;

        $row = $DB->get_record('local_tituscontentlibrary_added', ['contentid' => $contentid]);
        if (!$row) {
            throw new \Exception("No _added row found for content id: {$contentid}");
        }
        if ($row->status !== \local_tituscontentlibrary\local\content_status::COMPLETED) {
            throw new \Exception(
                "Content {$contentid} did not complete (status={$row->status}, error={$row->errormessage})"
            );
        }
        if (empty($row->courseid)) {
            throw new \Exception("Content {$contentid} completed but no courseid was recorded");
        }
        if (!$DB->record_exists('course', ['id' => $row->courseid])) {
            throw new \Exception("Recorded course {$row->courseid} for {$contentid} does not exist");
        }

        // There must be at least one SCORM instance in the created course.
        if (!$DB->record_exists('scorm', ['course' => $row->courseid])) {
            throw new \Exception("Course {$row->courseid} has no SCORM activity");
        }

        // And the recorded course module must exist and be a scorm module.
        $scormmoduleid = $DB->get_field('modules', 'id', ['name' => 'scorm'], MUST_EXIST);
        if (!$DB->record_exists('course_modules', [
            'id'     => $row->cmid,
            'course' => $row->courseid,
            'module' => $scormmoduleid,
        ])) {
            throw new \Exception(
                "Recorded SCORM course module {$row->cmid} not found in course {$row->courseid}"
            );
        }
    }
}
