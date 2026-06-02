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

namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;

/**
 * Unit tests for catalogue_manager.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\local\catalogue_manager
 */
class catalogue_manager_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Build a simple content_dto fixture.
     */
    private function make_dto(string $id, string $category = 'Leadership'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Title ' . $id,
            description: 'Desc',
            short_description: 'Short',
            thumbnail_url: '',
            category: $category,
            tags: [],
            duration_minutes: 10,
            is_featured: false,
            is_new: false,
        );
    }

    /**
     * Build a mock API client that returns $items exactly $times times.
     */
    private function make_mock(array $items, int $times): titus_api_client_interface {
        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->expects($this->exactly($times))
             ->method('get_catalogue')
             ->willReturn($items);
        return $mock;
    }

    /**
     * First call must hit the API; a second call within TTL must read from cache.
     */
    public function test_first_call_hits_api_second_call_hits_cache(): void {
        $items = [$this->make_dto('c1')];
        $mock  = $this->make_mock($items, 1); // exactly 1 API call expected

        $manager = new catalogue_manager($mock);

        $result1 = $manager->get_catalogue();
        $result2 = $manager->get_catalogue(); // should be cached

        $this->assertCount(1, $result1);
        $this->assertCount(1, $result2);
    }

    /**
     * Calling get_catalogue(true) must bypass the cache and hit the API each time.
     */
    public function test_force_refresh_bypasses_cache(): void {
        $items = [$this->make_dto('c1')];
        $mock  = $this->make_mock($items, 2); // exactly 2 API calls expected

        $manager = new catalogue_manager($mock);

        $manager->get_catalogue(true);
        $manager->get_catalogue(true);
    }

    /**
     * Degraded mode: API fails with no cached data → return empty array, not an exception.
     */
    public function test_degraded_mode_returns_empty_when_api_fails_and_no_cache(): void {
        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')
             ->willThrowException(new \RuntimeException('API down'));

        $manager = new catalogue_manager($mock);
        $result  = $manager->get_catalogue();

        // Consume the expected debugging() call so the test framework doesn't fail.
        $this->assertDebuggingCalled('[titus_catalogue] API error: API down', DEBUG_NORMAL);

        $this->assertSame([], $result);
        $this->assertFalse($manager->is_stale());
    }

    /**
     * Degraded mode: API fails but a previous cache exists → return stale data, mark stale.
     */
    public function test_degraded_mode_returns_stale_cache_when_api_fails(): void {
        $items = [$this->make_dto('c1'), $this->make_dto('c2')];

        // First: successful fetch (populates cache).
        $mock_ok = $this->createMock(titus_api_client_interface::class);
        $mock_ok->method('get_catalogue')->willReturn($items);
        (new catalogue_manager($mock_ok))->get_catalogue(true);

        // Second: API fails → should fall back to stale cache.
        $mock_fail = $this->createMock(titus_api_client_interface::class);
        $mock_fail->method('get_catalogue')
                  ->willThrowException(new \RuntimeException('API down'));

        $manager = new catalogue_manager($mock_fail);
        $result  = $manager->get_catalogue(true); // force refresh to trigger API call

        // Consume the expected debugging() call.
        $this->assertDebuggingCalled('[titus_catalogue] API error: API down', DEBUG_NORMAL);

        $this->assertCount(2, $result);
        $this->assertTrue($manager->is_stale());
    }

    /**
     * A successful API call must persist the timestamp to config_plugins.
     */
    public function test_last_successful_refresh_updated_on_success(): void {
        $before = time();
        $items  = [$this->make_dto('c1')];
        $mock   = $this->make_mock($items, 1);

        $manager = new catalogue_manager($mock);
        $manager->get_catalogue(true);

        $stored = (int) get_config('local_tituscontentlibrary', 'last_successful_refresh');
        $this->assertGreaterThanOrEqual($before, $stored);
        $this->assertLessThanOrEqual(time(), $stored);
    }

    /**
     * get_categories() must return a sorted, unique list of category names.
     */
    public function test_get_categories_returns_sorted_unique_list(): void {
        $items = [
            $this->make_dto('c1', 'Sales'),
            $this->make_dto('c2', 'Leadership'),
            $this->make_dto('c3', 'Sales'),       // duplicate
            $this->make_dto('c4', 'compliance'),  // different case
            $this->make_dto('c5', 'Agile'),
        ];

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn($items);

        $manager    = new catalogue_manager($mock);
        $categories = $manager->get_categories();

        // Unique: Sales, Leadership, compliance, Agile → 4 entries.
        $this->assertCount(4, $categories);

        // Sorted case-insensitively: Agile, compliance, Leadership, Sales.
        $this->assertSame('Agile',      $categories[0]);
        $this->assertSame('compliance', $categories[1]);
        $this->assertSame('Leadership', $categories[2]);
        $this->assertSame('Sales',      $categories[3]);
    }
}
