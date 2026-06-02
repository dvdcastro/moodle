<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\local\manage_row_status
 */
class manage_row_status_test extends \advanced_testcase {

    private function row(string $status, int $courseid = 1, string $contentid = 'sim-001'): \stdClass {
        return (object)[
            'status'    => $status,
            'courseid'  => $courseid,
            'contentid' => $contentid,
        ];
    }

    public function test_ok_when_completed_course_exists_and_in_catalogue(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::COMPLETED, 1, 'sim-001'),
            true,
            ['sim-001']
        );
        $this->assertSame(manage_row_status::OK, $result);
    }

    public function test_course_deleted_when_course_missing(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::COMPLETED, 99, 'sim-001'),
            false,
            ['sim-001']
        );
        $this->assertSame(manage_row_status::COURSE_DELETED, $result);
    }

    public function test_out_of_date_when_not_in_catalogue(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::COMPLETED, 1, 'sim-old'),
            true,
            ['sim-001', 'sim-002']
        );
        $this->assertSame(manage_row_status::OUT_OF_DATE, $result);
    }

    public function test_failed_shows_failed(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::FAILED),
            true,
            ['sim-001']
        );
        $this->assertSame(manage_row_status::FAILED, $result);
    }

    public function test_processing_shows_processing(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::PROCESSING),
            true,
            ['sim-001']
        );
        $this->assertSame(manage_row_status::PROCESSING, $result);
    }

    public function test_pending_shows_pending(): void {
        $result = manage_row_status::derive(
            $this->row(content_status::PENDING),
            true,
            ['sim-001']
        );
        $this->assertSame(manage_row_status::PENDING, $result);
    }
}
