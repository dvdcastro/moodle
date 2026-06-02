<?php
namespace local_tituscontentlibrary\api;

defined('MOODLE_INTERNAL') || die();

/**
 * @covers \local_tituscontentlibrary\api\client_factory
 */
class client_factory_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        client_factory::reset();
    }

    protected function tearDown(): void {
        client_factory::reset();
        parent::tearDown();
    }

    public function test_get_client_returns_real_client_by_default(): void {
        $client = client_factory::get_client();
        $this->assertInstanceOf(titus_api_client_interface::class, $client);
        $this->assertInstanceOf(titus_api_client::class, $client);
    }

    public function test_set_test_client_overrides_real_client(): void {
        $mock = $this->createMock(titus_api_client_interface::class);
        client_factory::set_test_client($mock);

        $this->assertSame($mock, client_factory::get_client());
    }

    public function test_reset_clears_override(): void {
        $mock = $this->createMock(titus_api_client_interface::class);
        client_factory::set_test_client($mock);
        client_factory::reset();

        $this->assertInstanceOf(titus_api_client::class, client_factory::get_client());
    }

    public function test_set_test_client_null_clears_override(): void {
        $mock = $this->createMock(titus_api_client_interface::class);
        client_factory::set_test_client($mock);
        client_factory::set_test_client(null);

        $this->assertInstanceOf(titus_api_client::class, client_factory::get_client());
    }
}
