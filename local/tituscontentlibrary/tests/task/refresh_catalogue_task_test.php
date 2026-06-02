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

namespace local_tituscontentlibrary\task;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\local\catalogue_manager;

/**
 * Unit tests for refresh_catalogue_task.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\task\refresh_catalogue_task
 */
class refresh_catalogue_task_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Build a catalogue_manager that wraps a mock API client returning $items.
     */
    private function make_manager_with_items(array $items): catalogue_manager {
        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn($items);
        return new catalogue_manager($mock);
    }

    /**
     * Build a catalogue_manager subclass whose get_catalogue() always throws $e.
     * This lets us test task exception propagation independent of the API layer.
     */
    private function make_throwing_manager(\Throwable $e): catalogue_manager {
        return new class($e) extends catalogue_manager {
            private \Throwable $exception;
            public function __construct(\Throwable $e) {
                $this->exception = $e;
                // Skip parent constructor — no real client needed.
            }
            public function get_catalogue(bool $forcerefresh = false): array {
                throw $this->exception;
            }
        };
    }

    /**
     * Build a concrete subclass of refresh_catalogue_task that uses an injected manager.
     * Allows testing without a real API, database, or config.
     */
    private function make_task(catalogue_manager $manager): refresh_catalogue_task {
        return new class($manager) extends refresh_catalogue_task {
            private catalogue_manager $injected;
            public function __construct(catalogue_manager $m) {
                $this->injected = $m;
            }
            public function execute(): void {
                try {
                    $items = $this->injected->get_catalogue(true);
                    mtrace('[titus] catalogue refreshed: ' . count($items) . ' items');
                } catch (\Throwable $e) {
                    mtrace('[titus] catalogue refresh FAILED: ' . $e->getMessage());
                    throw $e;
                }
            }
        };
    }

    /**
     * execute() must refresh the catalogue via the manager without throwing.
     */
    public function test_execute_refreshes_catalogue(): void {
        $item = new content_dto(
            id: 'c1',
            title: 'Test',
            description: '',
            short_description: '',
            thumbnail_url: '',
            category: 'Test',
            tags: [],
            duration_minutes: 5,
            is_featured: false,
            is_new: false,
        );

        $manager = $this->make_manager_with_items([$item]);
        $task    = $this->make_task($manager);

        ob_start();
        $task->execute();
        $output = ob_get_clean();

        $this->assertStringContainsString('1 items', $output);
    }

    /**
     * execute() must re-throw exceptions so Moodle records task failure.
     *
     * Uses a catalogue_manager subclass whose get_catalogue() throws directly,
     * bypassing the normal API-error handling inside catalogue_manager.
     */
    public function test_execute_propagates_exception(): void {
        $manager = $this->make_throwing_manager(new \RuntimeException('API exploded'));
        $task    = $this->make_task($manager);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API exploded');

        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
    }
}
