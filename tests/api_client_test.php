<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_balancedgroups;

use local_balancedgroups\local\api_client;
use local_balancedgroups\local\api_exception;

/**
 * Tests for the HTTP status handling of the API client (the network call is replaced).
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\local\api_client
 */
final class api_client_test extends \advanced_testcase {
    /**
     * A client whose send() returns a canned answer.
     *
     * @param array $answer
     * @return api_client
     */
    private function client(array $answer): api_client {
        return new class ($answer) extends api_client {
            /** @var array */
            private array $answer;

            /**
             * Constructor.
             *
             * @param array $answer
             */
            public function __construct(array $answer) {
                parent::__construct('https://example.invalid/addon/groups', 5);
                $this->answer = $answer;
            }

            /**
             * Canned answer.
             *
             * @param string $body
             * @return array
             */
            protected function send(string $body): array {
                return $this->answer + ['errno' => 0, 'error' => '', 'httpcode' => 200, 'body' => ''];
            }
        };
    }

    /**
     * HTTP 200 returns the decoded JSON.
     */
    public function test_ok(): void {
        $this->resetAfterTest();
        $out = $this->client(['body' => '{"status":"ok","groups":[]}'])->solve(['rows' => []]);
        $this->assertSame('ok', $out['status']);
    }

    /**
     * Every failure becomes an api_exception with its own message.
     *
     * @dataProvider failure_provider
     * @param array $answer
     * @param string $errorcode
     */
    public function test_failures(array $answer, string $errorcode): void {
        $this->resetAfterTest();
        try {
            $this->client($answer)->solve(['rows' => []]);
            $this->fail('Exception expected');
        } catch (api_exception $e) {
            $this->assertSame($errorcode, $e->errorcode);
        }
    }

    /**
     * Failure cases.
     *
     * @return array
     */
    public static function failure_provider(): array {
        $json = '{"status":"error","message":"m","errors":["e1"],"warnings":["w1"]}';
        return [
            'timeout' => [['errno' => 28, 'httpcode' => 0], 'errortimeout'],
            'no connection' => [['errno' => 7, 'error' => 'refused', 'httpcode' => 0], 'errorconnect'],
            'blocked by moodle' => [['httpcode' => 0, 'error' => 'The URL is blocked.'], 'errorconnect'],
            'bad input' => [['httpcode' => 400, 'body' => $json], 'error400'],
            'too large' => [['httpcode' => 413, 'body' => $json], 'error413'],
            'no grouping' => [['httpcode' => 422, 'body' => $json], 'error422'],
            'rate limited' => [['httpcode' => 429, 'body' => '<html>'], 'error429'],
            'busy' => [['httpcode' => 503, 'body' => $json], 'error503'],
            'server error' => [['httpcode' => 500, 'body' => ''], 'errorhttp'],
            'not json' => [['httpcode' => 200, 'body' => '<html>'], 'errorbadresponse'],
        ];
    }

    /**
     * A 422 keeps the server's reasons for display.
     */
    public function test_422_details(): void {
        $this->resetAfterTest();
        $client = $this->client(['httpcode' => 422,
            'body' => '{"status":"error","message":"m","errors":["e1"],"warnings":["w1","m"]}']);
        try {
            $client->solve([]);
            $this->fail('Exception expected');
        } catch (api_exception $e) {
            $this->assertSame(422, $e->httpcode);
            $this->assertSame('m', $e->servermessage);
            $this->assertSame(['e1', 'w1'], $e->details);
        }
    }

    /**
     * The rate-limit key is a hash of the site address, not anything about a user.
     */
    public function test_site_key(): void {
        global $CFG;
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', api_client::site_key());
        $this->assertStringNotContainsString(parse_url($CFG->wwwroot, PHP_URL_HOST), api_client::site_key());
    }
}
