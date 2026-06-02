<?php
namespace local_tituscontentlibrary\local;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\local\log_sanitizer
 */
class log_sanitization_test extends \advanced_testcase {

    public function test_sanitize_with_key_masks_secret(): void {
        $result = log_sanitizer::sanitize_with_key(
            'Request failed — X-Licence-Key: MY_SECRET_KEY_123',
            'MY_SECRET_KEY_123'
        );

        $this->assertStringNotContainsString('MY_SECRET_KEY_123', $result);
        $this->assertStringContainsString('***', $result);
    }

    public function test_sanitize_with_empty_key_returns_message_unchanged(): void {
        $message = 'Some log message with no key';
        $this->assertSame($message, log_sanitizer::sanitize_with_key($message, ''));
    }

    public function test_sanitize_replaces_all_occurrences(): void {
        $result = log_sanitizer::sanitize_with_key(
            'Key=SECRET and also KEY=SECRET here',
            'SECRET'
        );

        $this->assertStringNotContainsString('SECRET', $result);
        // Both occurrences replaced.
        $this->assertSame('Key=*** and also KEY=*** here', $result);
    }

    public function test_sanitize_from_config_uses_licence_key(): void {
        $this->resetAfterTest(true);

        // No licence key configured: should return message unchanged.
        set_config('licencekey', '', 'local_tituscontentlibrary');
        $message = 'plain message';
        $this->assertSame($message, log_sanitizer::sanitize($message));
    }

    public function test_sanitize_message_without_key_is_unchanged(): void {
        $result = log_sanitizer::sanitize_with_key('No sensitive data here.', 'MYKEY');
        $this->assertSame('No sensitive data here.', $result);
    }
}
