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
 * An optional CSV of extra per-student data, matched to course users on username, email or ID number.
 *
 * The parsed CSV is kept only in the user's session cache while the wizard is open.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class csv_source {
    /** @var int Most data rows read from a CSV. */
    public const MAX_ROWS = 5000;

    /** @var int Most columns read from a CSV. */
    public const MAX_COLUMNS = 60;

    /** @var array Accepted header spellings for each user field the CSV can be matched on. */
    private const MATCH_ALIASES = [
        'username' => ['username', 'user name', 'login'],
        'email' => ['email', 'e mail', 'email address', 'e mail address'],
        'idnumber' => ['idnumber', 'id number'],
    ];

    /** @var string[] Header spellings of a "must be together" column (the same list the server accepts). */
    public const TOGETHER_ALIASES = ['must be together', 'together', 'keep together'];

    /** @var string[] Header spellings of a "must be apart" column. */
    public const APART_ALIASES = ['must be apart', 'apart', 'keep apart'];

    /**
     * Normalise a header for comparison.
     *
     * @param string $header
     * @return string
     */
    public static function norm_header(string $header): string {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header);
        return trim(preg_replace('/[\s_\-]+/u', ' ', \core_text::strtolower($header)));
    }

    /**
     * Parse CSV text.
     *
     * @param string $content File content.
     * @param string $encoding E.g. 'UTF-8'.
     * @param string $delimiter Delimiter name as in csv_import_reader::get_delimiter_list() ('comma', 'semicolon'...).
     * @return array ['headers' => string[], 'rows' => list of string[]]
     * @throws moodle_exception When the file cannot be read or is too large.
     */
    public static function parse(string $content, string $encoding, string $delimiter): array {
        global $CFG;
        require_once($CFG->libdir . '/csvlib.class.php');
        $iid = \csv_import_reader::get_new_iid('local_balancedgroups');
        $reader = new \csv_import_reader($iid, 'local_balancedgroups');
        try {
            $count = $reader->load_csv_content($content, $encoding, $delimiter);
            if ($count === false) {
                throw new moodle_exception('errorcsv', 'local_balancedgroups', '', s($reader->get_error()));
            }
            $headers = array_map('trim', $reader->get_columns() ?: []);
            if (count($headers) > self::MAX_COLUMNS) {
                throw new moodle_exception('errorcsvcolumns', 'local_balancedgroups', '', self::MAX_COLUMNS);
            }
            $rows = [];
            $reader->init();
            while (($line = $reader->next()) !== false) {
                if (count($rows) >= self::MAX_ROWS) {
                    throw new moodle_exception('errorcsvrows', 'local_balancedgroups', '', self::MAX_ROWS);
                }
                $rows[] = array_map(static fn($v) => trim((string)$v), array_pad($line, count($headers), ''));
            }
        } finally {
            $reader->close();
            $reader->cleanup(true);
        }
        if (!$rows) {
            throw new moodle_exception('errorcsvempty', 'local_balancedgroups');
        }
        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Columns the CSV can be matched on.
     *
     * @param string[] $headers
     * @return array Column index => user field ('username', 'email' or 'idnumber').
     */
    public static function match_columns(array $headers): array {
        $out = [];
        foreach ($headers as $i => $h) {
            $n = self::norm_header($h);
            foreach (self::MATCH_ALIASES as $field => $aliases) {
                if (in_array($n, $aliases, true)) {
                    $out[$i] = $field;
                }
            }
        }
        return $out;
    }

    /**
     * Index of the first column whose header is one of the given aliases.
     *
     * @param string[] $headers
     * @param string[] $aliases
     * @return int|null
     */
    public static function find_column(array $headers, array $aliases): ?int {
        foreach ($headers as $i => $h) {
            if (in_array(self::norm_header($h), $aliases, true)) {
                return $i;
            }
        }
        return null;
    }

    /**
     * Match CSV rows to users.
     *
     * @param array $csv ['headers', 'rows'] from parse().
     * @param int $column Index of the match column.
     * @param string $userfield 'username', 'email' or 'idnumber'.
     * @param array $users User id => object with that field.
     * @return array ['rows' => [user id => string[]], 'unmatched' => int (CSV rows with no user),
     *               'duplicates' => int (users found on more than one row; the first row is used)]
     */
    public static function match(array $csv, int $column, string $userfield, array $users): array {
        $index = [];
        foreach ($users as $userid => $user) {
            $v = \core_text::strtolower(trim((string)($user->$userfield ?? '')));
            if ($v !== '' && !isset($index[$v])) {
                $index[$v] = (int)$userid;
            }
        }
        $out = ['rows' => [], 'unmatched' => 0, 'duplicates' => 0];
        foreach ($csv['rows'] as $row) {
            $v = \core_text::strtolower(trim((string)($row[$column] ?? '')));
            if ($v === '' || !isset($index[$v])) {
                $out['unmatched']++;
                continue;
            }
            $userid = $index[$v];
            if (isset($out['rows'][$userid])) {
                $out['duplicates']++;
                continue;
            }
            $out['rows'][$userid] = $row;
        }
        return $out;
    }

    /**
     * Whether every non-blank value of a column is a number (suggests balancing by average).
     *
     * @param array $rows
     * @param int $column
     * @return bool False for an empty column.
     */
    public static function is_numeric_column(array $rows, int $column): bool {
        $any = false;
        foreach ($rows as $row) {
            $num = request_builder::to_number($row[$column] ?? null);
            if ($num === false) {
                return false;
            }
            $any = $any || $num !== null;
        }
        return $any;
    }
}
