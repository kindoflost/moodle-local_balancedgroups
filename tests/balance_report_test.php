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

use local_balancedgroups\local\balance_report;
use local_balancedgroups\local\request_builder;

/**
 * Tests for the locally computed balance tables.
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\local\balance_report
 */
final class balance_report_test extends \advanced_testcase {
    /**
     * Counts per category and averages per group, with the real labels.
     */
    public function test_build(): void {
        $groups = [[1, 2], [3, 4]];
        $fields = [
            ['key' => 'city', 'label' => 'City', 'kind' => request_builder::KIND_CATEGORY, 'priority' => 1],
            ['key' => 'grade', 'label' => 'Grade', 'kind' => request_builder::KIND_NUMBER, 'priority' => 2],
        ];
        $values = [
            1 => ['city' => 'Wichita', 'grade' => 70.0],
            2 => ['city' => 'La Plata', 'grade' => 80.0],
            3 => ['city' => 'wichita', 'grade' => null],
            4 => ['city' => null, 'grade' => '90'],
        ];
        $out = balance_report::build(
            $groups,
            ['Group A', 'Group B'],
            $fields,
            $values,
            ['city' => ['score' => 75.0, 'balanced' => true]]
        );

        $cells = static fn(array $row) => array_column($row['cells'], 'value');
        $this->assertSame(['Group', 'People counted', 'Wichita', 'La Plata'], array_column($out[0]['columns'], 'name'));
        $this->assertSame(['Group A', '2', '1', '1'], $cells($out[0]['rows'][0]));
        $this->assertSame(['Group B', '1', '1', '0'], $cells($out[0]['rows'][1]));
        $this->assertSame(['Overall', '3', '2 (67%)', '1 (33%)'], $cells($out[0]['overall']));
        $this->assertStringContainsString('75', $out[0]['score']);

        $this->assertSame(['Group A', '2', format_float(75, 2)], $cells($out[1]['rows'][0]));
        $this->assertSame(['Group B', '1', format_float(90, 2)], $cells($out[1]['rows'][1]));
        $this->assertSame(['Overall', '3', format_float(80, 2)], $cells($out[1]['overall']));
        $this->assertNull($out[1]['score']);
    }
}
