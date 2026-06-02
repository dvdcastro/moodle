<?php
namespace local_tituscontentlibrary\api;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/tituscontentlibrary/tests/fixtures/mock_titus_api_client.php');

use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;
use local_tituscontentlibrary\api\exception\titus_network_exception;
use local_tituscontentlibrary\tests\fixtures\mock_titus_api_client;

/**
 * @covers \local_tituscontentlibrary\tests\fixtures\mock_titus_api_client
 */
class mock_titus_api_client_test extends \advanced_testcase {

    private function make_dto(string $id = 'sim-001'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Test',
            description: '',
            short_description: '',
            thumbnail_url: '',
            category: '',
            tags: [],
            duration_minutes: 0,
            is_featured: false,
            is_new: false,
        );
    }

    public function test_returns_configured_catalogue(): void {
        $dto  = $this->make_dto('item-1');
        $mock = (new mock_titus_api_client())->with_catalogue([$dto]);

        $result = $mock->get_catalogue();

        $this->assertCount(1, $result);
        $this->assertSame('item-1', $result[0]->id);
    }

    public function test_returns_configured_signed_url(): void {
        $mock = (new mock_titus_api_client())
            ->with_signed_url('https://s3.amazonaws.com/bucket/file.zip', 600);

        $dto = $mock->get_signed_download_url('sim-001');

        $this->assertInstanceOf(signed_url_dto::class, $dto);
        $this->assertSame('https://s3.amazonaws.com/bucket/file.zip', $dto->url);
        $this->assertSame(600, $dto->expires_in);
    }

    public function test_tracks_call_counts(): void {
        $mock = new mock_titus_api_client();
        $mock->get_catalogue();
        $mock->get_catalogue();
        $mock->health();

        $this->assertSame(2, $mock->get_call_count('get_catalogue'));
        $this->assertSame(1, $mock->get_call_count('health'));
        $this->assertSame(0, $mock->get_call_count('download_to'));
    }

    public function test_fail_on_throws_configured_exception(): void {
        $exception = new titus_network_exception('Simulated failure');
        $mock = (new mock_titus_api_client())->fail_on('get_catalogue', $exception);

        $this->expectException(titus_network_exception::class);
        $mock->get_catalogue();
    }

    public function test_download_to_copies_zip_fixture(): void {
        $tmpdir  = make_temp_directory('titustest_mock');
        $zippath = $tmpdir . '/source.zip';
        file_put_contents($zippath, 'fake zip content');

        $destpath = $tmpdir . '/dest.zip';
        $mock = (new mock_titus_api_client())->with_zip_fixture($zippath);
        $mock->download_to('https://s3.amazonaws.com/fake.zip', $destpath);

        $this->assertFileExists($destpath);
        $this->assertSame('fake zip content', file_get_contents($destpath));
    }

    public function test_health_returns_true_by_default(): void {
        $this->assertTrue((new mock_titus_api_client())->health());
    }

    public function test_health_returns_false_when_fail_on_configured(): void {
        $mock = (new mock_titus_api_client())->fail_on('health', new \RuntimeException('down'));
        $this->assertFalse($mock->health());
    }
}
