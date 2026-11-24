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

namespace local_balancedgroups\local;

use moodle_exception;

/**
 * Calls the Balanced Group Maker API (POST /addon/groups, synchronous) through Moodle's curl class.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_client {
    /** @var string Default endpoint. */
    public const DEFAULT_URL = 'https://group-maker.kindoflost.com/addon/groups';

    /** @var int Default timeout in seconds (the server can queue up to 45 s and then solve). */
    public const DEFAULT_TIMEOUT = 90;

    /** @var string */
    private string $url;

    /** @var int */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param string|null $url Endpoint; null reads the plugin setting.
     * @param int|null $timeout Seconds; null reads the plugin setting.
     */
    public function __construct(?string $url = null, ?int $timeout = null) {
        $this->url = $url ?? (get_config('local_balancedgroups', 'apiurl') ?: self::DEFAULT_URL);
        $timeout = $timeout ?? (int)get_config('local_balancedgroups', 'timeout');
        $this->timeout = $timeout > 0 ? $timeout : self::DEFAULT_TIMEOUT;
    }

    /**
     * Anonymous per-site key for the server's rate limit: a hash of the site URL, never a user.
     *
     * @return string
     */
    public static function site_key(): string {
        global $CFG;
        return hash('sha256', 'local_balancedgroups|' . $CFG->wwwroot);
    }

    /**
     * Send one request and return the decoded success answer.
     *
     * @param array $payload
     * @return array Decoded JSON of an HTTP 200 answer.
     * @throws api_exception With a message for the teacher (and server details where useful).
     */
    public function solve(array $payload): array {
        $body = json_encode($payload);
        if ($body === false) {
            throw new api_exception('errorbadrequest', 0);
        }
        \core_php_time_limit::raise($this->timeout + 30);
        $res = $this->send($body);

        if ($res['errno'] == 28) {
            throw new api_exception('errortimeout', 0, $this->timeout);
        }
        if ($res['errno'] || !$res['httpcode']) {
            throw new api_exception('errorconnect', 0, s(trim($res['error'] ?: ('curl ' . $res['errno']))));
        }
        $json = json_decode((string)$res['body'], true);
        $code = (int)$res['httpcode'];
        $servermessage = is_array($json) && isset($json['message']) && is_string($json['message']) ? $json['message'] : '';
        switch ($code) {
            case 200:
                if (!is_array($json)) {
                    throw new api_exception('errorbadresponse', $code);
                }
                return $json;
            case 400:
                throw new api_exception('error400', $code, null, $servermessage);
            case 413:
                throw new api_exception('error413', $code, null, $servermessage);
            case 422:
                $details = [];
                foreach (['errors', 'warnings'] as $k) {
                    foreach ((array)($json[$k] ?? []) as $m) {
                        if (is_string($m) && $m !== '' && $m !== $servermessage) {
                            $details[] = $m;
                        }
                    }
                }
                throw new api_exception('error422', $code, null, $servermessage, $details);
            case 429:
                throw new api_exception('error429', $code);
            case 503:
                throw new api_exception('error503', $code, null, $servermessage);
            default:
                throw new api_exception('errorhttp', $code, $code);
        }
    }

    /**
     * Do the HTTP POST. Separate so tests can replace it.
     *
     * @param string $body JSON body.
     * @return array ['errno' => int, 'error' => string, 'httpcode' => int, 'body' => string]
     */
    protected function send(string $body): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_balancedgroups');
        $curl = new \curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Addon-User: ' . self::site_key(),
            'X-Client: moodle-local_balancedgroups/' . ($plugin->release ?? ''),
        ]);
        $response = $curl->post($this->url, $body, [
            'CURLOPT_TIMEOUT' => $this->timeout,
            'CURLOPT_CONNECTTIMEOUT' => min(20, $this->timeout),
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_USERAGENT' => 'MoodleBot local_balancedgroups',
        ]);
        $info = $curl->get_info();
        return [
            'errno' => (int)$curl->get_errno(),
            'error' => (string)($curl->error ?? ''),
            'httpcode' => (int)($info['http_code'] ?? 0),
            'body' => (string)$response,
        ];
    }
}
