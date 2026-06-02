<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * @covers \local_tituscontentlibrary\local\catalogue_search
 */
class catalogue_search_test extends \advanced_testcase {

    private function dto(string $id, string $title, string $category, array $tags = [], string $shortdesc = ''): content_dto {
        return new content_dto(
            id: $id,
            title: $title,
            description: 'Long description',
            short_description: $shortdesc ?: $title,
            thumbnail_url: 'https://example.com/thumb.jpg',
            category: $category,
            tags: $tags,
            duration_minutes: 30,
            is_featured: false,
            is_new: false,
        );
    }

    private function catalogue(): array {
        return [
            $this->dto('a', 'PHP Basics', 'Technology', ['php', 'programming']),
            $this->dto('b', 'Leadership 101', 'Management', ['leadership', 'soft-skills']),
            $this->dto('c', 'Advanced PHP', 'Technology', ['php', 'advanced']),
            $this->dto('d', 'Excel Mastery', 'Productivity', ['excel', 'office'], 'Master Excel formulas'),
        ];
    }

    public function test_empty_filters_return_all(): void {
        $result = catalogue_search::filter($this->catalogue(), '', '');
        $this->assertCount(4, $result);
    }

    public function test_text_filter_matches_title(): void {
        $result = catalogue_search::filter($this->catalogue(), 'PHP', '');
        $this->assertCount(2, $result);
        $ids = array_column($result, 'id');
        $this->assertContains('a', $ids);
        $this->assertContains('c', $ids);
    }

    public function test_text_filter_case_insensitive(): void {
        $result = catalogue_search::filter($this->catalogue(), 'php', '');
        $this->assertCount(2, $result);
    }

    public function test_text_filter_matches_short_description(): void {
        $result = catalogue_search::filter($this->catalogue(), 'formulas', '');
        $this->assertCount(1, $result);
        $this->assertSame('d', $result[0]->id);
    }

    public function test_text_filter_matches_tag(): void {
        $result = catalogue_search::filter($this->catalogue(), 'leadership', '');
        $this->assertCount(1, $result);
        $this->assertSame('b', $result[0]->id);
    }

    public function test_category_filter_exact_slug(): void {
        $result = catalogue_search::filter($this->catalogue(), '', 'technology');
        $this->assertCount(2, $result);
        foreach ($result as $item) {
            $this->assertSame('Technology', $item->category);
        }
    }

    public function test_category_filter_combined_with_query(): void {
        $result = catalogue_search::filter($this->catalogue(), 'Advanced', 'technology');
        $this->assertCount(1, $result);
        $this->assertSame('c', $result[0]->id);
    }

    public function test_no_matches_returns_empty(): void {
        $result = catalogue_search::filter($this->catalogue(), 'zzznomatch', '');
        $this->assertCount(0, $result);
    }

    public function test_returns_indexed_array(): void {
        $result = catalogue_search::filter($this->catalogue(), 'PHP', '');
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
    }
}
