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
 * Turns Moodle users and their field values into an anonymised API request, and reads the answer.
 *
 * What leaves the site: a random token per person (new on every request), field codes f1..fn,
 * category values recoded c1..cn per field, numbers as numbers, and together/apart codes recoded
 * T1.. / A1... No user ids, names, emails or field names are sent.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request_builder {
    /** @var int Server limit: people per request. */
    public const MAX_PEOPLE = 300;

    /** @var int Server limit: groups per request. */
    public const MAX_GROUPS = 60;

    /** @var int Server limit: fields to balance per request. */
    public const MAX_FIELDS = 15;

    /** @var string Field balanced as categories (each value spread proportionally). */
    public const KIND_CATEGORY = 'category';

    /** @var string Field balanced as numbers (equal group averages). */
    public const KIND_NUMBER = 'number';

    /** @var callable Returns a new random token. */
    private $tokengenerator;

    /** @var bool Shuffle the order of people before sending. */
    private bool $shuffle;

    /**
     * Constructor.
     *
     * @param callable|null $tokengenerator Returns a random string; injectable for tests.
     * @param bool $shuffle Send people in random order, so "Run again" can find another equally good grouping.
     */
    public function __construct(?callable $tokengenerator = null, bool $shuffle = true) {
        $this->tokengenerator = $tokengenerator ?? static function (): string {
            return 'p' . bin2hex(random_bytes(6));
        };
        $this->shuffle = $shuffle;
    }

    /**
     * Normalised key of a category value, matching how the server compares values (trimmed, case-insensitive).
     *
     * @param mixed $value
     * @return string|null Null for a blank value.
     */
    public static function category_key($value): ?string {
        $label = self::category_label($value);
        return $label === null ? null : \core_text::strtolower($label);
    }

    /**
     * Display form of a category value.
     *
     * @param mixed $value
     * @return string|null Null for a blank value.
     */
    public static function category_label($value): ?string {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        }
        if (is_float($value) && floor($value) == $value && abs($value) < 1e15) {
            $value = (string)(int)$value;
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    /**
     * A value as a number.
     *
     * @param mixed $value
     * @return float|null|false Null for blank, false when it is not a number.
     */
    public static function to_number($value) {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return is_nan((float)$value) ? null : (float)$value;
        }
        if (is_bool($value) || is_array($value) || is_object($value)) {
            return false;
        }
        $s = trim((string)$value);
        if ($s === '') {
            return null;
        }
        return is_numeric($s) ? (float)$s : false;
    }

    /**
     * Split a together/apart cell into codes ("1, 3" or "a;b").
     *
     * @param string|null $cell
     * @return array Normalised key => display code.
     */
    public static function split_codes(?string $cell): array {
        $out = [];
        if ($cell === null) {
            return $out;
        }
        foreach (preg_split('/[;,]/', $cell) as $code) {
            $code = trim($code);
            if ($code !== '') {
                $key = \core_text::strtolower($code);
                if (!isset($out[$key])) {
                    $out[$key] = $code;
                }
            }
        }
        return $out;
    }

    /**
     * Build the anonymised request.
     *
     * @param array $people User id => ['name' => string, 'values' => [fieldkey => value],
     *                      'together' => string|null, 'apart' => string|null].
     * @param array $fields List of ['key' => string, 'label' => string, 'priority' => int, 'kind' => string].
     * @param array $size ['groups' => int|null, 'min_size' => int|null, 'max_size' => int|null].
     * @return request
     * @throws moodle_exception When the input breaks a limit or a number field holds text.
     */
    public function build(array $people, array $fields, array $size): request {
        $n = count($people);
        if ($n < 2) {
            throw new moodle_exception('errortoofewpeople', 'local_balancedgroups', '', $n);
        }
        if ($n > self::MAX_PEOPLE) {
            throw new moodle_exception(
                'errortoomanypeople',
                'local_balancedgroups',
                '',
                (object)['count' => $n, 'max' => self::MAX_PEOPLE]
            );
        }
        if (count($fields) > self::MAX_FIELDS) {
            throw new moodle_exception(
                'errortoomanyfields',
                'local_balancedgroups',
                '',
                (object)['count' => count($fields), 'max' => self::MAX_FIELDS]
            );
        }
        $groups = isset($size['groups']) ? (int)$size['groups'] : 0;
        $minsize = isset($size['min_size']) ? (int)$size['min_size'] : 0;
        $maxsize = isset($size['max_size']) ? (int)$size['max_size'] : 0;
        if ($groups > self::MAX_GROUPS) {
            throw new moodle_exception(
                'errortoomanygroups',
                'local_balancedgroups',
                '',
                (object)['count' => $groups, 'max' => self::MAX_GROUPS]
            );
        }
        if ($groups < 1 && $minsize < 1 && $maxsize < 1) {
            throw new moodle_exception('errornosize', 'local_balancedgroups');
        }

        $req = new request();
        $header = ['person', 'must be together', 'must be apart'];
        $priorities = ['priority', null, null];
        $i = 0;
        foreach (array_values($fields) as $field) {
            $i++;
            $code = 'f' . $i;
            $req->fieldcodes[$code] = $field['key'];
            $req->labels[$code] = $field['label'];
            $req->categories[$field['key']] = [];
            $header[] = $code;
            $priorities[] = max(1, (int)$field['priority']);
        }

        $userids = array_keys($people);
        if ($this->shuffle) {
            shuffle($userids);
        }

        $catcodes = [];
        $togcodes = [];
        $apartcodes = [];
        $body = [];
        foreach ($userids as $userid) {
            $person = $people[$userid];
            do {
                $token = ($this->tokengenerator)();
            } while (isset($req->tokens[$token]) || isset($req->labels[$token]));
            $req->tokens[$token] = (int)$userid;
            $req->labels[$token] = $person['name'] ?? (string)$userid;

            $row = [$token,
                $this->recode_codes($person['together'] ?? null, 'T', $togcodes, $req),
                $this->recode_codes($person['apart'] ?? null, 'A', $apartcodes, $req)];
            foreach (array_values($fields) as $field) {
                $key = $field['key'];
                $value = $person['values'][$key] ?? null;
                if ($field['kind'] === self::KIND_NUMBER) {
                    $num = self::to_number($value);
                    if ($num === false) {
                        throw new moodle_exception(
                            'errornotnumeric',
                            'local_balancedgroups',
                            '',
                            (object)['field' => s($field['label']), 'value' => s((string)$value),
                            'person' => s($person['name'] ?? (string)$userid)]
                        );
                    }
                    $row[] = $num;
                } else {
                    $catkey = self::category_key($value);
                    if ($catkey === null) {
                        $row[] = null;
                        continue;
                    }
                    if (!isset($catcodes[$key][$catkey])) {
                        $catcodes[$key][$catkey] = 'c' . (count($catcodes[$key] ?? []) + 1);
                        $req->categories[$key][$catcodes[$key][$catkey]] = self::category_label($value);
                    }
                    $row[] = $catcodes[$key][$catkey];
                }
            }
            $body[] = $row;
        }

        $req->payload = ['rows' => array_merge([$header, $priorities], $body)];
        if ($groups > 0) {
            $req->payload['groups'] = $groups;
        }
        if ($minsize > 0) {
            $req->payload['min_size'] = $minsize;
        }
        if ($maxsize > 0) {
            $req->payload['max_size'] = $maxsize;
        }
        return $req;
    }

    /**
     * Recode one together/apart cell.
     *
     * @param string|null $cell
     * @param string $prefix 'T' or 'A'.
     * @param array $map Normalised code => sent code (updated).
     * @param request $req Labels are added here.
     * @return string|null
     */
    private function recode_codes(?string $cell, string $prefix, array &$map, request $req): ?string {
        $out = [];
        foreach (self::split_codes($cell) as $key => $display) {
            if (!isset($map[$key])) {
                $map[$key] = $prefix . (count($map) + 1);
                $req->labels[$map[$key]] = $display;
            }
            $out[] = $map[$key];
        }
        return $out ? implode(',', $out) : null;
    }

    /**
     * Replace the codes inside a server message (f1, a person token, T2...) with what they stand for.
     *
     * The server names fields, people and together/apart codes by the codes it received, sometimes
     * quoted and sometimes not, so every code standing as a whole word is replaced.
     *
     * @param request $req
     * @param string $message
     * @return string
     */
    public static function translate(request $req, string $message): string {
        if (!$req->labels) {
            return $message;
        }
        $codes = array_keys($req->labels);
        usort($codes, static fn($a, $b) => strlen((string)$b) <=> strlen((string)$a));
        $pattern = '/(?<![A-Za-z0-9_])(' . implode('|', array_map(static fn($c) => preg_quote((string)$c, '/'), $codes)) .
            ')(?![A-Za-z0-9_])/';
        return preg_replace_callback($pattern, static function (array $m) use ($req): string {
            return (string)$req->labels[$m[1]];
        }, $message);
    }

    /**
     * Read a successful (HTTP 200) API answer.
     *
     * @param request $req The request that was sent.
     * @param array $response Decoded JSON.
     * @return array ['groups' => list of lists of user ids, 'balance' => [fieldkey => ['score' => float|null,
     *               'balanced' => bool]], 'warnings' => string[], 'notes' => string[], 'meaning' => string]
     * @throws moodle_exception When the answer does not hold every person exactly once.
     */
    public static function decode(request $req, array $response): array {
        if (($response['status'] ?? '') !== 'ok' || !isset($response['groups']) || !is_array($response['groups'])) {
            throw new moodle_exception('errorbadresponse', 'local_balancedgroups');
        }
        $seen = [];
        $groups = [];
        foreach ($response['groups'] as $group) {
            if (!is_array($group) || !isset($group['members']) || !is_array($group['members'])) {
                throw new moodle_exception('errorbadresponse', 'local_balancedgroups');
            }
            $members = [];
            foreach ($group['members'] as $token) {
                if (!is_string($token) || !isset($req->tokens[$token]) || isset($seen[$token])) {
                    throw new moodle_exception('errorbadresponse', 'local_balancedgroups');
                }
                $seen[$token] = true;
                $members[] = $req->tokens[$token];
            }
            if ($members) {
                $groups[] = $members;
            }
        }
        if (count($seen) !== count($req->tokens)) {
            throw new moodle_exception('errorbadresponse', 'local_balancedgroups');
        }
        $balance = [];
        foreach (($response['balance'] ?? []) as $b) {
            $code = $b['field'] ?? null;
            if (is_string($code) && isset($req->fieldcodes[$code])) {
                $balance[$req->fieldcodes[$code]] = [
                    'score' => isset($b['balance_score']) && is_numeric($b['balance_score'])
                        ? (float)$b['balance_score'] : null,
                    'balanced' => !empty($b['balanced']),
                ];
            }
        }
        $translate = static function ($list) use ($req): array {
            $out = [];
            foreach ((array)$list as $msg) {
                if (is_string($msg) && $msg !== '') {
                    $out[] = self::translate($req, $msg);
                }
            }
            return $out;
        };
        return [
            'groups' => $groups,
            'balance' => $balance,
            'warnings' => $translate($response['warnings'] ?? []),
            'notes' => $translate($response['notes'] ?? []),
            'meaning' => is_string($response['meaning'] ?? null) ? $response['meaning'] : '',
        ];
    }
}
