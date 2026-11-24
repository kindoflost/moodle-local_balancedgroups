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

/**
 * Per-field balance tables for the preview, computed locally from the real values.
 *
 * The server only ever saw recoded values; this class shows the teacher the same balance with
 * the real category names, from data that never left the site.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class balance_report {
    /**
     * Build the tables.
     *
     * @param array $groups List of lists of user ids (the decoded answer).
     * @param array $groupnames Name of each group, same order.
     * @param array $fields List of ['key', 'label', 'kind', 'priority'].
     * @param array $values User id => [field key => raw value].
     * @param array $serverbalance Field key => ['score' => float|null, 'balanced' => bool].
     * @return array Template context: list of fields, each with 'columns', 'rows' and 'overall'.
     */
    public static function build(
        array $groups,
        array $groupnames,
        array $fields,
        array $values,
        array $serverbalance = []
    ): array {
        $out = [];
        foreach ($fields as $field) {
            $key = $field['key'];
            $isnumber = $field['kind'] === request_builder::KIND_NUMBER;
            $rows = [];
            if ($isnumber) {
                $columns = [get_string('group'), get_string('peoplecounted', 'local_balancedgroups'),
                    get_string('average', 'local_balancedgroups')];
                $all = [];
                foreach ($groups as $i => $members) {
                    $nums = [];
                    foreach ($members as $userid) {
                        $num = request_builder::to_number($values[$userid][$key] ?? null);
                        if (is_float($num)) {
                            $nums[] = $num;
                        }
                    }
                    $all = array_merge($all, $nums);
                    $rows[] = self::row([$groupnames[$i] ?? (string)($i + 1), count($nums), self::avg($nums)]);
                }
                $overall = self::row([get_string('overall', 'local_balancedgroups'), count($all), self::avg($all)]);
            } else {
                // Categories in order of first appearance, keyed like the server compares them.
                $labels = [];
                foreach ($groups as $members) {
                    foreach ($members as $userid) {
                        $v = $values[$userid][$key] ?? null;
                        $ck = request_builder::category_key($v);
                        if ($ck !== null && !isset($labels[$ck])) {
                            $labels[$ck] = request_builder::category_label($v);
                        }
                    }
                }
                $columns = array_merge(
                    [get_string('group'), get_string('peoplecounted', 'local_balancedgroups')],
                    array_values($labels)
                );
                $totals = array_fill_keys(array_keys($labels), 0);
                $totaln = 0;
                foreach ($groups as $i => $members) {
                    $counts = array_fill_keys(array_keys($labels), 0);
                    $n = 0;
                    foreach ($members as $userid) {
                        $ck = request_builder::category_key($values[$userid][$key] ?? null);
                        if ($ck !== null) {
                            $counts[$ck]++;
                            $n++;
                        }
                    }
                    foreach ($counts as $ck => $c) {
                        $totals[$ck] += $c;
                    }
                    $totaln += $n;
                    $rows[] = self::row(array_merge([$groupnames[$i] ?? (string)($i + 1), $n], array_values($counts)));
                }
                $cells = [get_string('overall', 'local_balancedgroups'), $totaln];
                foreach ($totals as $c) {
                    $cells[] = $totaln ? get_string(
                        'countshare',
                        'local_balancedgroups',
                        (object)['count' => $c, 'share' => round(100 * $c / $totaln)]
                    ) : '0';
                }
                $overall = self::row($cells);
            }
            $sb = $serverbalance[$key] ?? null;
            $score = ($sb && $sb['balanced'] && $sb['score'] !== null)
                ? get_string('balancescore', 'local_balancedgroups', format_float($sb['score'], 1)) : null;
            $out[] = [
                'label' => $field['label'],
                'kind' => get_string($isnumber ? 'kindnumber' : 'kindcategory', 'local_balancedgroups'),
                'priority' => (int)($field['priority'] ?? 0),
                'score' => $score,
                'notbalanced' => $sb !== null && !$sb['balanced'],
                'columns' => array_map(static fn($c) => ['name' => $c], $columns),
                'rows' => $rows,
                'overall' => $overall,
            ];
        }
        return $out;
    }

    /**
     * Average as display text.
     *
     * @param float[] $nums
     * @return string
     */
    private static function avg(array $nums): string {
        return $nums ? format_float(array_sum($nums) / count($nums), 2) : '-';
    }

    /**
     * A table row for the template.
     *
     * @param array $cells
     * @return array
     */
    private static function row(array $cells): array {
        return ['cells' => array_map(static fn($c) => ['value' => (string)$c], array_values($cells))];
    }
}
