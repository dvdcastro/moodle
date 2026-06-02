<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\local\monitoring::render_indicator
 */
class monitoring_indicator_render_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_renders_success_badge_when_no_failures(): void {
        set_config(monitoring::CFG_FAILURE_STREAK, 0, 'local_tituscontentlibrary');

        $html = monitoring::render_indicator();

        $this->assertStringContainsString('text-bg-success', $html);
    }

    public function test_renders_warning_badge_when_below_threshold(): void {
        set_config(monitoring::CFG_FAILURE_STREAK, 1, 'local_tituscontentlibrary');
        set_config('failurestreakthreshold', 3, 'local_tituscontentlibrary');

        $html = monitoring::render_indicator();

        $this->assertStringContainsString('text-bg-warning', $html);
    }

    public function test_renders_danger_badge_at_threshold(): void {
        set_config(monitoring::CFG_FAILURE_STREAK, 3, 'local_tituscontentlibrary');
        set_config('failurestreakthreshold', 3, 'local_tituscontentlibrary');

        $html = monitoring::render_indicator();

        $this->assertStringContainsString('text-bg-danger', $html);
    }

    public function test_renders_danger_badge_above_threshold(): void {
        set_config(monitoring::CFG_FAILURE_STREAK, 10, 'local_tituscontentlibrary');
        set_config('failurestreakthreshold', 3, 'local_tituscontentlibrary');

        $html = monitoring::render_indicator();

        $this->assertStringContainsString('text-bg-danger', $html);
    }

    public function test_render_indicator_does_not_call_api(): void {
        // Simply confirm render_indicator() returns a non-empty HTML string
        // without throwing (which would indicate an API call was attempted).
        $html = monitoring::render_indicator();
        $this->assertIsString($html);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('badge', $html);
    }
}
