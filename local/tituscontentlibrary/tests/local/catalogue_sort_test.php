<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * @covers \local_tituscontentlibrary\local\catalogue_sort
 */
class catalogue_sort_test extends \advanced_testcase {

    private function dto(string $id, string $title, string $category = 'General',
            int $duration = 30, bool $featured = false, bool $isnew = false, ?int $updatedat = null): content_dto {
        return new content_dto(
            id: $id,
            title: $title,
            description: '',
            short_description: '',
            thumbnail_url: '',
            category: $category,
            subcategory: '',
            tags: [],
            duration_minutes: $duration,
            version: '1.0.0',
            is_featured: $featured,
            is_new: $isnew,
            updated_at: $updatedat,
        );
    }

    public function test_az_sort(): void {
        $items = [
            $this->dto('c', 'Zebra'),
            $this->dto('a', 'Apple'),
            $this->dto('b', 'Mango'),
        ];
        $result = catalogue_sort::sort($items, 'az');
        $this->assertSame('a', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('c', $result[2]->id);
    }

    public function test_za_sort(): void {
        $items = [
            $this->dto('a', 'Apple'),
            $this->dto('c', 'Zebra'),
            $this->dto('b', 'Mango'),
        ];
        $result = catalogue_sort::sort($items, 'za');
        $this->assertSame('c', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('a', $result[2]->id);
    }

    public function test_duration_sort(): void {
        $items = [
            $this->dto('c', 'Long', 'G', 120),
            $this->dto('a', 'Short', 'G', 5),
            $this->dto('b', 'Medium', 'G', 30),
        ];
        $result = catalogue_sort::sort($items, 'duration');
        $this->assertSame('a', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('c', $result[2]->id);
    }

    public function test_category_sort(): void {
        $items = [
            $this->dto('c', 'C', 'Technology'),
            $this->dto('a', 'A', 'Finance'),
            $this->dto('b', 'B', 'Management'),
        ];
        $result = catalogue_sort::sort($items, 'category');
        $this->assertSame('Finance', $result[0]->category);
        $this->assertSame('Management', $result[1]->category);
        $this->assertSame('Technology', $result[2]->category);
    }

    public function test_featured_sort(): void {
        $items = [
            $this->dto('a', 'Apple', 'G', 30, false),
            $this->dto('b', 'Banana', 'G', 30, true),
            $this->dto('c', 'Cherry', 'G', 30, false),
        ];
        $result = catalogue_sort::sort($items, 'featured');
        $this->assertTrue($result[0]->is_featured);
        $this->assertFalse($result[1]->is_featured);
        $this->assertFalse($result[2]->is_featured);
    }

    public function test_new_sort(): void {
        $items = [
            $this->dto('a', 'Apple', 'G', 30, false, false),
            $this->dto('b', 'Banana', 'G', 30, false, true),
        ];
        $result = catalogue_sort::sort($items, 'new');
        $this->assertTrue($result[0]->is_new);
        $this->assertFalse($result[1]->is_new);
    }

    public function test_newest_sort_nulls_last(): void {
        $items = [
            $this->dto('a', 'A', 'G', 30, false, false, null),
            $this->dto('b', 'B', 'G', 30, false, false, 1000),
            $this->dto('c', 'C', 'G', 30, false, false, 2000),
        ];
        $result = catalogue_sort::sort($items, 'newest');
        $this->assertSame('c', $result[0]->id);
        $this->assertSame('b', $result[1]->id);
        $this->assertSame('a', $result[2]->id);
    }

    public function test_invalid_sort_falls_back_to_az(): void {
        $items = [
            $this->dto('b', 'Zebra'),
            $this->dto('a', 'Apple'),
        ];
        $result = catalogue_sort::sort($items, 'nonexistent');
        $this->assertSame('a', $result[0]->id);
    }

    public function test_does_not_mutate_input(): void {
        $items = [
            $this->dto('b', 'Zebra'),
            $this->dto('a', 'Apple'),
        ];
        $original_first_id = $items[0]->id;
        catalogue_sort::sort($items, 'az');
        $this->assertSame($original_first_id, $items[0]->id);
    }
}
