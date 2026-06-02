<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\local\monitoring
 */
class monitoring_stats_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_get_stats_returns_zeros_when_no_data(): void {
        $stats = monitoring::get_stats();

        $this->assertSame(0, $stats['last_success']);
        $this->assertSame(0, $stats['failure_streak']);
        $this->assertSame(0, $stats['total_refreshes']);
    }

    public function test_record_success_sets_last_success_and_increments_total(): void {
        $before = time();
        monitoring::record_success();
        $after  = time();

        $stats = monitoring::get_stats();
        $this->assertGreaterThanOrEqual($before, $stats['last_success']);
        $this->assertLessThanOrEqual($after, $stats['last_success']);
        $this->assertSame(1, $stats['total_refreshes']);
    }

    public function test_record_success_clears_failure_streak(): void {
        // Set up a streak manually.
        set_config(monitoring::CFG_FAILURE_STREAK, 5, 'local_tituscontentlibrary');

        monitoring::record_success();

        $stats = monitoring::get_stats();
        $this->assertSame(0, $stats['failure_streak']);
    }

    public function test_record_failure_increments_streak(): void {
        monitoring::record_failure();
        monitoring::record_failure();

        $stats = monitoring::get_stats();
        $this->assertSame(2, $stats['failure_streak']);
    }

    public function test_record_success_accumulates_total(): void {
        monitoring::record_success();
        monitoring::record_success();
        monitoring::record_success();

        $stats = monitoring::get_stats();
        $this->assertSame(3, $stats['total_refreshes']);
    }

    public function test_record_failure_does_not_reset_total_refreshes(): void {
        monitoring::record_success(); // total = 1
        monitoring::record_failure();

        $stats = monitoring::get_stats();
        $this->assertSame(1, $stats['total_refreshes']);
        $this->assertSame(1, $stats['failure_streak']);
    }

    public function test_failure_streak_resets_after_success(): void {
        monitoring::record_failure();
        monitoring::record_failure();
        monitoring::record_success();
        monitoring::record_failure();

        $stats = monitoring::get_stats();
        $this->assertSame(1, $stats['failure_streak']);
    }
}
