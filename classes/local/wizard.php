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

use html_writer;
use moodle_exception;
use stdClass;

/**
 * Helpers for the wizard page (index.php): step indicator, chosen fields, and the solve itself.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class wizard {
    /**
     * Render the step indicator.
     *
     * @param string $current setup, details or preview.
     * @return string HTML
     */
    public static function steps(string $current): string {
        global $OUTPUT;
        $steps = [];
        $done = true;
        foreach (['setup', 'details', 'preview'] as $i => $name) {
            $active = $name === $current;
            if ($active) {
                $done = false;
            }
            $steps[] = ['number' => $i + 1, 'name' => get_string('step' . $name, 'local_balancedgroups'),
                'active' => $active, 'done' => $done && !$active];
        }
        return $OUTPUT->render_from_template('local_balancedgroups/steps', ['steps' => $steps]);
    }

    /**
     * The fields chosen in step 1 that are still available, in catalogue order.
     *
     * @param source $source
     * @param array $setup
     * @return array List of field definitions.
     */
    public static function chosen_fields(source $source, array $setup): array {
        $out = [];
        foreach ($source->fields() as $key => $field) {
            if (!empty($setup['fields'][$key])) {
                $out[] = $field;
            }
        }
        return $out;
    }

    /**
     * Users matched to CSV rows with the chosen match column.
     *
     * @param array $csv
     * @param int $matchcol
     * @param array $users
     * @return array As csv_source::match().
     */
    public static function csv_match(array $csv, int $matchcol, array $users): array {
        $matchcols = csv_source::match_columns($csv['headers']);
        if (!isset($matchcols[$matchcol])) {
            $matchcol = (int)array_key_first($matchcols);
        }
        return csv_source::match($csv, $matchcol, $matchcols[$matchcol], $users);
    }

    /**
     * Build the request from the saved choices, call the API and store the result in the state.
     *
     * @param stdClass $course
     * @param source $source
     * @param array $state Wizard state (updated with 'result').
     * @return string|null Error message (HTML-safe) or null on success.
     */
    public static function run(stdClass $course, source $source, array &$state): ?string {
        $setup = $state['setup'];
        $details = $state['details'];
        $users = $source->users((int)$setup['roleid'], (int)$setup['groupid']);
        $userids = array_map('intval', array_keys($users));

        $fields = [];
        foreach (self::chosen_fields($source, $setup) as $field) {
            $fields[] = ['key' => $field['key'], 'label' => $field['label'],
                'priority' => (int)($details['prio'][$field['key']] ?? 1),
                'kind' => $details['kind'][$field['key']] ?? $field['kind']];
        }
        $values = $source->values(array_column($fields, 'key'), $userids);

        $csv = $state['csv'] ?? null;
        $csvrows = [];
        if ($csv) {
            $match = self::csv_match($csv, (int)($details['csvmatch'] ?? -1), $users);
            $csvrows = $match['rows'];
            foreach (($details['csvprio'] ?? []) as $col => $prio) {
                if ((int)$prio < 1 || !isset($csv['headers'][$col])) {
                    continue;
                }
                $key = 'csv_' . $col;
                $fields[] = ['key' => $key, 'label' => $csv['headers'][$col], 'priority' => (int)$prio,
                    'kind' => $details['csvkind'][$col] ?? request_builder::KIND_CATEGORY];
                foreach ($userids as $userid) {
                    $values[$userid][$key] = $csvrows[$userid][$col] ?? null;
                }
            }
        }

        $people = [];
        $togcol = $csv ? csv_source::find_column($csv['headers'], csv_source::TOGETHER_ALIASES) : null;
        $apacol = $csv ? csv_source::find_column($csv['headers'], csv_source::APART_ALIASES) : null;
        foreach ($users as $userid => $user) {
            $together = $apart = null;
            if (!empty($setup['usecodes'])) {
                if (($details['codesource'] ?? 'typed') === 'csv') {
                    $together = $togcol !== null ? ($csvrows[$userid][$togcol] ?? null) : null;
                    $apart = $apacol !== null ? ($csvrows[$userid][$apacol] ?? null) : null;
                } else {
                    $together = $details['codes'][$userid]['t'] ?? null;
                    $apart = $details['codes'][$userid]['a'] ?? null;
                }
            }
            $people[$userid] = ['name' => fullname($user), 'values' => $values[$userid] ?? [],
                'together' => $together, 'apart' => $apart];
        }

        $size = $details['sizemode'] === 'range'
            ? ['min_size' => (int)$details['minsize'] ?: null, 'max_size' => (int)$details['maxsize'] ?: null]
            : ['groups' => (int)$details['numgroups']];

        $builder = new request_builder();
        try {
            $request = $builder->build($people, $fields, $size);
            $answer = (new api_client())->solve($request->payload);
            $decoded = request_builder::decode($request, $answer);
        } catch (api_exception $e) {
            $msg = $e->getMessage();
            $extra = [];
            if ($e->servermessage !== '') {
                $extra[] = s(request_builder::translate($request, $e->servermessage));
            }
            foreach ($e->details as $d) {
                $extra[] = s(request_builder::translate($request, $d));
            }
            if ($extra) {
                $msg .= html_writer::alist($extra);
            }
            return $msg;
        } catch (moodle_exception $e) {
            return $e->getMessage();
        }

        $names = [];
        foreach ($users as $userid => $user) {
            $names[$userid] = fullname($user);
        }
        $state['result'] = $decoded + ['fields' => $fields, 'values' => $values, 'names' => $names, 'time' => time()];
        return null;
    }
}
