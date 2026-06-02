<?php
namespace local_tituscontentlibrary\external;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * @covers \local_tituscontentlibrary\external\search_catalogue
 */
class search_catalogue_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    private function make_dto(string $id, string $title, string $category = 'Technology',
            array $tags = []): content_dto {
        return new content_dto(
            id: $id,
            title: $title,
            description: 'Long desc',
            short_description: 'Short ' . $title,
            thumbnail_url: 'https://example.com/thumb.jpg',
            category: $category,
            tags: $tags,
            duration_minutes: 30,
            is_featured: false,
            is_new: false,
        );
    }

    private function prime_cache(array $dtos): void {
        $cache = \cache::make('local_tituscontentlibrary', 'catalogue');
        $cache->set('all', $dtos);
        $cache->set('meta', ['fetched_at' => time(), 'stale' => false]);
    }

    private function set_user_with_view_cap(): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        assign_capability('local/tituscontentlibrary:viewlibrary', CAP_ALLOW, $role, \context_system::instance()->id, true);
        role_assign($role, $user->id, \context_system::instance()->id);
        $this->setUser($user);
        return $user;
    }

    public function test_returns_all_when_no_filters(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([
            $this->make_dto('a', 'Alpha'),
            $this->make_dto('b', 'Beta', 'Management'),
        ]);

        $result = search_catalogue::execute('', '', 'az');
        $this->assertCount(2, $result['items']);
    }

    public function test_filters_by_query(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([
            $this->make_dto('a', 'PHP Basics', 'Technology', ['php']),
            $this->make_dto('b', 'Leadership', 'Management'),
        ]);

        $result = search_catalogue::execute('PHP', '', 'az');
        $this->assertCount(1, $result['items']);
        $this->assertSame('a', $result['items'][0]['id']);
    }

    public function test_filters_by_category(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([
            $this->make_dto('a', 'Alpha', 'Technology'),
            $this->make_dto('b', 'Beta', 'Management'),
        ]);

        $result = search_catalogue::execute('', 'technology', 'az');
        $this->assertCount(1, $result['items']);
        $this->assertSame('a', $result['items'][0]['id']);
    }

    public function test_sorts_az(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([
            $this->make_dto('z', 'Zebra'),
            $this->make_dto('a', 'Apple'),
        ]);

        $result = search_catalogue::execute('', '', 'az');
        $this->assertSame('a', $result['items'][0]['id']);
        $this->assertSame('z', $result['items'][1]['id']);
    }

    public function test_sorts_za(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([
            $this->make_dto('a', 'Apple'),
            $this->make_dto('z', 'Zebra'),
        ]);

        $result = search_catalogue::execute('', '', 'za');
        $this->assertSame('z', $result['items'][0]['id']);
        $this->assertSame('a', $result['items'][1]['id']);
    }

    public function test_returns_publishedat_field(): void {
        $this->set_user_with_view_cap();
        $this->prime_cache([$this->make_dto('a', 'Alpha')]);

        $result = search_catalogue::execute('', '', 'az');
        $this->assertArrayHasKey('publishedat', $result['items'][0]);
        $this->assertNull($result['items'][0]['publishedat']);
    }

    public function test_requires_view_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->prime_cache([$this->make_dto('a', 'Alpha')]);

        $this->expectException(\required_capability_exception::class);
        search_catalogue::execute('', '', 'az');
    }

    public function test_requires_login_not_guest(): void {
        $this->setGuestUser();
        $this->prime_cache([$this->make_dto('a', 'Alpha')]);

        $this->expectException(\required_capability_exception::class);
        search_catalogue::execute('', '', 'az');
    }
}
