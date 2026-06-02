<?php
namespace local_tituscontentlibrary\output;

defined('MOODLE_INTERNAL') || die();

use local_tituscontentlibrary\api\dto\content_dto;

/**
 * @covers \local_tituscontentlibrary\output\marketplace_page
 */
class marketplace_page_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    private function make_dto(string $id = 'sim-001', string $thumbnailurl = 'https://example.com/thumb.jpg'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Test Content',
            description: 'A test content item description',
            short_description: 'Short desc',
            thumbnail_url: $thumbnailurl,
            category: 'Technology',
            tags: ['php'],
            duration_minutes: 30,
            is_featured: true,
            is_new: false,
        );
    }

    public function test_export_for_template_structure(): void {
        global $PAGE;

        $dto        = $this->make_dto();
        $categories = ['Technology'];
        $page       = new marketplace_page([$dto], $categories, false);

        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertArrayHasKey('contents', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertArrayHasKey('allactive', $data);
        $this->assertArrayHasKey('emptystate', $data);
        $this->assertArrayHasKey('emptystatecontext', $data);
        $this->assertArrayHasKey('errorstate', $data);
        $this->assertArrayHasKey('stale', $data);

        $this->assertCount(1, $data['contents']);
        $this->assertFalse($data['emptystate']);
        $this->assertFalse($data['stale']);
        $this->assertFalse($data['errorstate']);
        $this->assertTrue($data['allactive']);

        $item = $data['contents'][0];
        $this->assertEquals('sim-001', $item['id']);
        $this->assertEquals('Test Content', $item['title']);
        $this->assertFalse($item['isadded']);
        $this->assertFalse($item['isloading']);
        $this->assertFalse($item['isqueued']);
        $this->assertFalse($item['haserror']);
        $this->assertEquals('', $item['errormessage']);
    }

    public function test_thumbnail_urls_are_sanitized(): void {
        global $PAGE;

        $dto  = $this->make_dto('sim-xss', 'javascript:alert(1)');
        $page = new marketplace_page([$dto], [], false);

        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertEmpty($data['contents'][0]['thumbnailurl']);
    }

    public function test_emptystate_true_when_catalogue_empty(): void {
        global $PAGE;

        $page = new marketplace_page([], [], false);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['emptystate']);
    }

    public function test_errorstate_propagated(): void {
        global $PAGE;

        $page = new marketplace_page([], [], false, true);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['errorstate']);
        $this->assertTrue($data['emptystatecontext']['nocatalogue']);
    }

    public function test_categories_built_with_slug(): void {
        global $PAGE;

        $dto  = $this->make_dto();
        $page = new marketplace_page([$dto], ['Leadership & Management', 'Technology'], false);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertCount(2, $data['categories']);
        $cat = $data['categories'][0];
        $this->assertArrayHasKey('id', $cat);
        $this->assertArrayHasKey('name', $cat);
        $this->assertFalse($cat['isactive']);
    }

    public function test_stale_alert_hidden_when_contents_empty(): void {
        global $PAGE;

        // Stale but empty — show stalecache empty-state, not the stale banner.
        $page = new marketplace_page([], [], true);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertFalse($data['stale']); // banner hidden
        $this->assertTrue($data['emptystatecontext']['stalecache']);
    }

    public function test_stale_banner_shown_when_contents_present(): void {
        global $PAGE;

        $dto  = $this->make_dto();
        $page = new marketplace_page([$dto], [], true);
        $data = $page->export_for_template($PAGE->get_renderer('core'));

        $this->assertTrue($data['stale']); // banner visible
        $this->assertFalse($data['emptystatecontext']['stalecache']);
    }
}
