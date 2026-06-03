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

use local_tituscontentlibrary\config;
use local_tituscontentlibrary\api\client_factory;
use local_tituscontentlibrary\api\titus_api_client_interface;
use local_tituscontentlibrary\api\exception\titus_auth_exception;
use local_tituscontentlibrary\api\exception\titus_forbidden_exception;
use local_tituscontentlibrary\api\exception\titus_network_exception;

/**
 * Unit tests for the licence-key connexion validator and the blocking save behaviour (MLFR-225).
 *
 * @package   local_tituscontentlibrary
 * @copyright 2026 Titus Learning
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \local_tituscontentlibrary\local\connexion_validator
 * @covers    \local_tituscontentlibrary\admin\setting_encryptedpassword_required
 */
class connexion_validator_test extends \advanced_testcase {

    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        // The admin setting subclass extends \admin_setting_encryptedpassword, which is
        // defined in adminlib.php — not autoloaded in the PHPUnit context.
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest(true);
        client_factory::set_test_client(null);
        // A configured base URL + stored (encrypted) key so probe() reaches the client.
        set_config('apibaseurl', 'https://api.example.com', 'local_tituscontentlibrary');
        set_config('licencekey', \core\encryption::encrypt('stored-key'), 'local_tituscontentlibrary');
    }

    protected function tearDown(): void {
        client_factory::set_test_client(null);
        parent::tearDown();
    }

    /**
     * Build a mock client whose get_catalogue() either returns rows or throws.
     */
    private function mock_client(?\Throwable $cataloguethrows = null): titus_api_client_interface {
        $mock = $this->createMock(titus_api_client_interface::class);
        if ($cataloguethrows !== null) {
            $mock->method('get_catalogue')->willThrowException($cataloguethrows);
        } else {
            $mock->method('get_catalogue')->willReturn([]);
        }
        return $mock;
    }

    // -----------------------------------------------------------------------
    // probe()
    // -----------------------------------------------------------------------

    public function test_probe_ok_when_catalogue_succeeds(): void {
        client_factory::set_test_client($this->mock_client());
        $this->assertSame(connexion_validator::OK, connexion_validator::probe());
    }

    public function test_probe_invalid_on_auth_exception(): void {
        client_factory::set_test_client($this->mock_client(new titus_auth_exception('401')));
        $this->assertSame(connexion_validator::INVALID, connexion_validator::probe());
    }

    public function test_probe_invalid_on_forbidden_exception(): void {
        client_factory::set_test_client($this->mock_client(new titus_forbidden_exception('403')));
        $this->assertSame(connexion_validator::INVALID, connexion_validator::probe());
    }

    public function test_probe_unreachable_on_network_error(): void {
        client_factory::set_test_client($this->mock_client(new titus_network_exception('timeout')));
        $this->assertSame(connexion_validator::UNREACHABLE, connexion_validator::probe());
    }

    public function test_probe_unreachable_when_unconfigured(): void {
        set_config('licencekey', '', 'local_tituscontentlibrary');
        $this->assertSame(connexion_validator::UNREACHABLE, connexion_validator::probe());
    }

    // -----------------------------------------------------------------------
    // setting_encryptedpassword_required::write_setting() — MLFR-225 acceptance
    // -----------------------------------------------------------------------

    private function make_setting(): \local_tituscontentlibrary\admin\setting_encryptedpassword_required {
        return new \local_tituscontentlibrary\admin\setting_encryptedpassword_required(
            'local_tituscontentlibrary/licencekey', 'Licence key', 'desc'
        );
    }

    /**
     * A rejected key must be blocked (non-empty error string) and the previously
     * stored key left intact — no full error page, nothing bad persisted.
     */
    public function test_rejected_key_is_blocked_and_rolled_back(): void {
        set_config('licencekey', \core\encryption::encrypt('old-good-key'), 'local_tituscontentlibrary');
        client_factory::set_test_client($this->mock_client(new titus_auth_exception('401')));

        $result = $this->make_setting()->write_setting('bad-key');

        $this->assertNotSame('', $result, 'an inline error string must be returned to block the save');
        $this->assertSame('old-good-key', config::get_licence_key(), 'the prior key must be preserved');
    }

    /**
     * A verified key saves normally and returns no error.
     */
    public function test_valid_key_saves(): void {
        client_factory::set_test_client($this->mock_client());

        $result = $this->make_setting()->write_setting('new-good-key');

        $this->assertSame('', $result);
        $this->assertSame('new-good-key', config::get_licence_key());
    }

    /**
     * A transient/network error does NOT block the save (brief §5.1): the key is
     * stored and no error string is returned.
     */
    public function test_unreachable_does_not_block_save(): void {
        client_factory::set_test_client($this->mock_client(new titus_network_exception('timeout')));

        $result = $this->make_setting()->write_setting('maybe-good-key');

        $this->assertSame('', $result);
        $this->assertSame('maybe-good-key', config::get_licence_key());
    }

    /**
     * An empty submission on the very first save is rejected as required.
     */
    public function test_empty_first_save_is_required(): void {
        set_config('licencekey', '', 'local_tituscontentlibrary');
        $result = $this->make_setting()->write_setting('');
        $this->assertNotSame('', $result);
    }
}
