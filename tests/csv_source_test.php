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

use local_balancedgroups\local\csv_source;

/**
 * Tests for the CSV reader and matcher.
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\local\csv_source
 */
final class csv_source_test extends \advanced_testcase {
    /**
     * Parse, detect columns, match on email.
     */
    public function test_parse_and_match(): void {
        $this->resetAfterTest();
        $content = "Email,Reading level,Must be together,keep_apart\n" .
            "ANA@example.com,3,a,\n" .
            "bo@example.com,high,,x\n" .
            "nobody@example.com,1,,\n" .
            "ana@example.com,9,,\n";
        $csv = csv_source::parse($content, 'UTF-8', 'comma');
        $this->assertSame(['Email', 'Reading level', 'Must be together', 'keep_apart'], $csv['headers']);
        $this->assertCount(4, $csv['rows']);

        $this->assertSame([0 => 'email'], csv_source::match_columns($csv['headers']));
        $this->assertSame(2, csv_source::find_column($csv['headers'], csv_source::TOGETHER_ALIASES));
        $this->assertSame(3, csv_source::find_column($csv['headers'], csv_source::APART_ALIASES));
        $this->assertFalse(csv_source::is_numeric_column($csv['rows'], 1));
        $this->assertTrue(csv_source::is_numeric_column([['1'], [''], ['2.5']], 0));

        $users = [
            5 => (object)['email' => 'ana@example.com'],
            6 => (object)['email' => 'Bo@Example.com'],
            7 => (object)['email' => 'cy@example.com'],
        ];
        $m = csv_source::match($csv, 0, 'email', $users);
        $this->assertSame([5, 6], array_keys($m['rows']));
        $this->assertSame('3', $m['rows'][5][1]);
        $this->assertSame(1, $m['unmatched']);
        $this->assertSame(1, $m['duplicates']);
    }

    /**
     * Semicolon files and the usual header spellings.
     */
    public function test_semicolon_and_headers(): void {
        $this->resetAfterTest();
        $csv = csv_source::parse("user name;ID number;score\nalice;A1;4\n", 'UTF-8', 'semicolon');
        $this->assertSame([0 => 'username', 1 => 'idnumber'], csv_source::match_columns($csv['headers']));
        $this->assertSame('ID number', $csv['headers'][1]);
    }

    /**
     * A header without data rows is refused.
     */
    public function test_empty(): void {
        $this->resetAfterTest();
        $this->expectException(\moodle_exception::class);
        csv_source::parse("email,score\n", 'UTF-8', 'comma');
    }
}
