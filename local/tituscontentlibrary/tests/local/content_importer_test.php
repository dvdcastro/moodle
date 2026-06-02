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

use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\dto\content_dto;
use local_tituscontentlibrary\api\dto\signed_url_dto;

/**
 * Tests for content_importer: the shared pipeline that downloads + creates course + SCORM.
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\local\content_importer
 */
class content_importer_test extends \advanced_testcase {

    /** @var string Temp dir used across tests in this class. */
    private string $tmpdir;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->tmpdir = make_temp_directory('titusimportertest');
        client_factory::set_test_client(null);
    }

    protected function tearDown(): void {
        client_factory::set_test_client(null);
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function make_valid_scorm_zip(string $name = 'test_scorm.zip'): string {
        global $CFG;
        $source = $CFG->dirroot . '/mod/scorm/tests/packages/singlescobasic.zip';
        $dest   = $this->tmpdir . '/' . $name;
        copy($source, $dest);
        return $dest;
    }

    private function make_invalid_scorm_zip(string $name = 'bad_scorm.zip'): string {
        $path = $this->tmpdir . '/' . $name;
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('readme.txt', 'not a SCORM package');
        $zip->close();
        return $path;
    }

    private function make_dto(string $id = 'content_001'): content_dto {
        return new content_dto(
            id: $id,
            title: 'Test Content ' . $id,
            description: 'Test description',
            short_description: 'Short',
            thumbnail_url: '',
            category: 'Leadership',
            tags: [],
            duration_minutes: 10,
            is_featured: false,
            is_new: false,
        );
    }

    private function make_mock_client(content_dto $dto, string $zippath): titus_api_client_interface {
        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300));
        $mock->method('download_to')
             ->willReturnCallback(function (string $url, string $dest) use ($zippath): void {
                 copy($zippath, $dest);
             });
        return $mock;
    }

    // -----------------------------------------------------------------------
    // Tests
    // -----------------------------------------------------------------------

    /**
     * Happy path: import() creates a course + SCORM and returns correct ids.
     */
    public function test_import_creates_course_and_returns_ids(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        $user    = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $cid     = 'import_happy';
        $dto     = $this->make_dto($cid);
        $zippath = $this->make_valid_scorm_zip();

        client_factory::set_test_client($this->make_mock_client($dto, $zippath));

        $result = (new content_importer())->import($cid, $user->id);

        $this->assertIsInt($result->courseid);
        $this->assertIsInt($result->cmid);
        $this->assertIsInt($result->scormid);
        $this->assertGreaterThan(0, $result->courseid);
        $this->assertGreaterThan(0, $result->cmid);
        $this->assertGreaterThan(0, $result->scormid);

        $this->assertTrue($DB->record_exists('course', ['id' => $result->courseid]));
        $this->assertTrue($DB->record_exists('scorm', ['id' => $result->scormid, 'course' => $result->courseid]));
        $this->assertTrue($DB->record_exists('course_modules', ['id' => $result->cmid, 'instance' => $result->scormid]));
    }

    /**
     * Course title and shortname are derived from the content_dto.
     */
    public function test_import_course_fullname_matches_dto_title(): void {
        global $DB, $CFG;
        require_once($CFG->dirroot . '/mod/scorm/lib.php');

        $user    = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $cid     = 'import_title_check';
        $dto     = $this->make_dto($cid);
        $zippath = $this->make_valid_scorm_zip('title.zip');

        client_factory::set_test_client($this->make_mock_client($dto, $zippath));

        $result = (new content_importer())->import($cid, $user->id);

        $course = $DB->get_record('course', ['id' => $result->courseid], '*', MUST_EXIST);
        $this->assertSame($dto->title, $course->fullname);
        $this->assertStringStartsWith('ML-' . $cid . '-', $course->shortname);
    }

    /**
     * An invalid ZIP (no imsmanifest.xml) must throw and leave no course in DB.
     */
    public function test_import_invalid_zip_throws_and_no_course_created(): void {
        global $DB;

        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $cid    = 'import_bad_zip';
        $dto    = $this->make_dto($cid);
        $badzip = $this->make_invalid_scorm_zip();

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/bad.zip', 'bad.zip', 300));
        $mock->method('download_to')
             ->willReturnCallback(function (string $url, string $dest) use ($badzip): void {
                 copy($badzip, $dest);
             });
        client_factory::set_test_client($mock);

        $coursesBefore = $DB->count_records('course');

        $this->expectException(\local_tituscontentlibrary\api\exception\titus_invalid_scorm_exception::class);
        (new content_importer())->import($cid, $user->id);

        $this->assertSame($coursesBefore, $DB->count_records('course'));
    }

    /**
     * A download failure (e.g. network error) must throw before any DB changes.
     */
    public function test_import_download_failure_throws(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $cid  = 'import_dl_fail';
        $dto  = $this->make_dto($cid);

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([$dto]);
        $mock->method('get_signed_download_url')
             ->willReturn(new signed_url_dto('https://cdn.example.com/missing.zip', 'missing.zip', 300));
        $mock->method('download_to')
             ->willThrowException(
                 new \local_tituscontentlibrary\api\exception\titus_not_found_exception('missing.zip')
             );
        client_factory::set_test_client($mock);

        $coursesBefore = $DB->count_records('course');

        $this->expectException(\local_tituscontentlibrary\api\exception\titus_not_found_exception::class);
        (new content_importer())->import($cid, $user->id);

        $this->assertSame($coursesBefore, $DB->count_records('course'));
    }

    /**
     * When contentid is not found in the catalogue, import() throws a moodle_exception.
     */
    public function test_import_unknown_contentid_throws(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $mock = $this->createMock(titus_api_client_interface::class);
        $mock->method('get_catalogue')->willReturn([]); // Empty catalogue.
        $mock->method('get_signed_download_url')->willReturn(
            new signed_url_dto('https://cdn.example.com/fake.zip', 'fake.zip', 300)
        );
        client_factory::set_test_client($mock);

        $this->expectException(\moodle_exception::class);
        (new content_importer())->import('unknown_id', $user->id);
    }
}
