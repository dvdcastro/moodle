<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * @covers \local_tituscontentlibrary\external\get_catalogue
 */
class get_catalogue_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    private function make_dto(string $id = 'sim-001'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Test Content',
            description: 'A test content item',
            short_description: 'Short desc',
            thumbnail_url: 'https://example.com/thumb.jpg',
            category: 'Technology',
            tags: ['php', 'moodle'],
            duration_minutes: 30,
            is_featured: false,
            is_new: true,
        );
    }

    private function prime_cache(array $dtos, bool $stale = false): void {
        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $cache->set('all', $dtos);
        $cache->set('meta', ['fetched_at' => time(), 'stale' => $stale]);
    }

    public function test_returns_catalogue_for_user_with_view_cap(): void {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $this->prime_cache([$this->make_dto()]);

        $result = get_catalogue::execute();

        $this->assertArrayHasKey('items', $result);
        $this->assertArrayHasKey('stale', $result);
        $this->assertCount(1, $result['items']);
        $this->assertFalse($result['stale']);
        $this->assertEquals('sim-001', $result['items'][0]['id']);
        $this->assertEquals('Test Content', $result['items'][0]['title']);
        $this->assertEquals(['php', 'moodle'], $result['items'][0]['tags']);
    }

    public function test_throws_without_view_cap(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->prime_cache([$this->make_dto()]);

        $this->expectException(\required_capability_exception::class);
        get_catalogue::execute();
    }

    public function test_returns_empty_catalogue_when_cache_empty(): void {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        // No cache prime — catalogue_manager will try the API and return [] on failure.
        set_config('apibaseurl', 'http://127.0.0.1:0/invalid', 'local_tituscontentlibrary');
        set_config('licencekey', '', 'local_tituscontentlibrary');

        $result = get_catalogue::execute();
        $this->resetDebugging(); // suppress expected API-failure debugging output
        $this->assertIsArray($result['items']);
    }

    public function test_stale_flag_reflects_cache_state(): void {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->setUser($user);

        $this->prime_cache([$this->make_dto()], true);

        $result = get_catalogue::execute();
        $this->assertTrue($result['stale']);
    }
}
