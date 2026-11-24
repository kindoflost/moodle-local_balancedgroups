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

use local_balancedgroups\local\request;
use local_balancedgroups\local\request_builder;

/**
 * Tests for the anonymising request builder.
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\local\request_builder
 */
final class request_builder_test extends \advanced_testcase {
    /**
     * A builder with predictable tokens and no shuffling.
     *
     * @return request_builder
     */
    private function builder(): request_builder {
        $n = 0;
        return new request_builder(static function () use (&$n): string {
            $n++;
            return 'tok' . $n;
        }, false);
    }

    /**
     * Four people with a category, a number and rule codes.
     *
     * @return array
     */
    private function people(): array {
        return [
            11 => ['name' => 'Ana Diaz', 'values' => ['city' => 'Wichita', 'grade' => 71.5],
                'together' => 'Pair', 'apart' => null],
            12 => ['name' => 'Bo Li', 'values' => ['city' => ' wichita ', 'grade' => '80'],
                'together' => 'pair', 'apart' => 'x; y'],
            13 => ['name' => 'Cy Ng', 'values' => ['city' => 'La Plata', 'grade' => null],
                'together' => '', 'apart' => 'X'],
            14 => ['name' => 'Di Fox', 'values' => ['city' => '', 'grade' => 90],
                'together' => null, 'apart' => 'y'],
        ];
    }

    /**
     * The fields used by the tests.
     *
     * @return array
     */
    private function fields(): array {
        return [
            ['key' => 'city', 'label' => 'City', 'priority' => 1, 'kind' => request_builder::KIND_CATEGORY],
            ['key' => 'grade', 'label' => 'Course total', 'priority' => 2, 'kind' => request_builder::KIND_NUMBER],
        ];
    }

    /**
     * The payload has the layout the API expects and recodes everything that could identify someone.
     */
    public function test_build_payload(): void {
        $req = $this->builder()->build($this->people(), $this->fields(), ['groups' => 2]);
        $rows = $req->payload['rows'];
        $this->assertSame(['person', 'must be together', 'must be apart', 'f1', 'f2'], $rows[0]);
        $this->assertSame(['priority', null, null, 1, 2], $rows[1]);
        $this->assertSame(['tok1', 'T1', null, 'c1', 71.5], $rows[2]);
        // Categories compare trimmed and case-insensitively, like the server.
        $this->assertSame(['tok2', 'T1', 'A1,A2', 'c1', 80.0], $rows[3]);
        $this->assertSame(['tok3', null, 'A1', 'c2', null], $rows[4]);
        $this->assertSame(['tok4', null, 'A2', null, 90.0], $rows[5]);
        $this->assertSame(2, $req->payload['groups']);
        $this->assertArrayNotHasKey('min_size', $req->payload);

        $this->assertSame(['tok1' => 11, 'tok2' => 12, 'tok3' => 13, 'tok4' => 14], $req->tokens);
        $this->assertSame(['f1' => 'city', 'f2' => 'grade'], $req->fieldcodes);
        $this->assertSame(['c1' => 'Wichita', 'c2' => 'La Plata'], $req->categories['city']);

        $json = json_encode($req->payload);
        foreach (['Ana', 'Diaz', 'Wichita', 'Plata', 'City', 'Course total', 'pair', '"x"', '11', '12'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }

    /**
     * Size options: a range instead of a number of groups.
     */
    public function test_build_size_range(): void {
        $req = $this->builder()->build($this->people(), $this->fields(), ['min_size' => 2, 'max_size' => 3]);
        $this->assertArrayNotHasKey('groups', $req->payload);
        $this->assertSame(2, $req->payload['min_size']);
        $this->assertSame(3, $req->payload['max_size']);
    }

    /**
     * Default tokens are random and different on every request.
     */
    public function test_default_tokens_are_random(): void {
        $b = new request_builder();
        $a = $b->build($this->people(), $this->fields(), ['groups' => 2]);
        $c = $b->build($this->people(), $this->fields(), ['groups' => 2]);
        $this->assertCount(4, $a->tokens);
        $this->assertEmpty(array_intersect(array_keys($a->tokens), array_keys($c->tokens)));
        foreach (array_keys($a->tokens) as $token) {
            $this->assertMatchesRegularExpression('/^p[0-9a-f]{12}$/', $token);
        }
    }

    /**
     * Inputs the server would reject are stopped before sending.
     *
     * @dataProvider invalid_provider
     * @param array $size
     * @param int $people
     * @param string $expected Language string id.
     */
    public function test_build_rejects(array $size, int $people, string $expected): void {
        $all = $this->people();
        $list = [];
        for ($i = 0; $i < $people; $i++) {
            $list[100 + $i] = $all[11 + ($i % 4)];
        }
        try {
            $this->builder()->build($list, $this->fields(), $size);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame($expected, $e->errorcode);
        }
    }

    /**
     * Invalid inputs.
     *
     * @return array
     */
    public static function invalid_provider(): array {
        return [
            'one person' => [['groups' => 1], 1, 'errortoofewpeople'],
            'too many people' => [['groups' => 2], 301, 'errortoomanypeople'],
            'too many groups' => [['groups' => 61], 4, 'errortoomanygroups'],
            'no size' => [[], 4, 'errornosize'],
        ];
    }

    /**
     * A field balanced as numbers must hold numbers.
     */
    public function test_build_rejects_text_in_number_field(): void {
        $fields = $this->fields();
        $fields[0]['kind'] = request_builder::KIND_NUMBER;
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage('Wichita');
        $this->builder()->build($this->people(), $fields, ['groups' => 2]);
    }

    /**
     * More than 15 fields is refused.
     */
    public function test_build_rejects_too_many_fields(): void {
        $fields = [];
        for ($i = 0; $i < 16; $i++) {
            $fields[] = ['key' => 'k' . $i, 'label' => 'K' . $i, 'priority' => 1, 'kind' => request_builder::KIND_CATEGORY];
        }
        $this->expectException(\moodle_exception::class);
        $this->builder()->build($this->people(), $fields, ['groups' => 2]);
    }

    /**
     * A successful answer maps back to user ids, balance scores and readable messages.
     */
    public function test_decode(): void {
        $req = $this->builder()->build($this->people(), $this->fields(), ['groups' => 2]);
        $answer = [
            'status' => 'ok',
            'groups' => [
                ['group' => 1, 'size' => 2, 'members' => ['tok1', 'tok2']],
                ['group' => 2, 'size' => 2, 'members' => ['tok3', 'tok4']],
            ],
            'balance' => [
                ['field' => 'f1', 'balanced' => true, 'balance_score' => 66.7],
                ['field' => 'f2', 'balanced' => false, 'balance_score' => null],
            ],
            'warnings' => ["Field 'f2' has the same value everywhere; nothing to balance."],
            'notes' => ['Together-group T1 has 2 people; tok3 and tok4 share apart-group A1.'],
            'meaning' => 'Proven within 5% of the best possible grouping.',
        ];
        $out = request_builder::decode($req, $answer);
        $this->assertSame([[11, 12], [13, 14]], $out['groups']);
        $this->assertSame(['score' => 66.7, 'balanced' => true], $out['balance']['city']);
        $this->assertFalse($out['balance']['grade']['balanced']);
        $this->assertSame(["Field 'Course total' has the same value everywhere; nothing to balance."], $out['warnings']);
        $this->assertSame(['Together-group Pair has 2 people; Cy Ng and Di Fox share apart-group x.'], $out['notes']);
    }

    /**
     * Answers that do not hold every person exactly once are refused.
     *
     * @dataProvider bad_answer_provider
     * @param array $groups
     */
    public function test_decode_rejects(array $groups): void {
        $req = $this->builder()->build($this->people(), $this->fields(), ['groups' => 2]);
        $this->expectException(\moodle_exception::class);
        request_builder::decode($req, ['status' => 'ok', 'groups' => $groups]);
    }

    /**
     * Bad answers.
     *
     * @return array
     */
    public static function bad_answer_provider(): array {
        return [
            'missing person' => [[['members' => ['tok1', 'tok2', 'tok3']]]],
            'unknown token' => [[['members' => ['tok1', 'tok2', 'tok3', 'tok9']]]],
            'twice' => [[['members' => ['tok1', 'tok2']], ['members' => ['tok2', 'tok3', 'tok4']]]],
            'not a list' => [[['members' => 'tok1']]],
        ];
    }

    /**
     * Value helpers.
     */
    public function test_helpers(): void {
        $this->assertSame(3.0, request_builder::to_number('3'));
        $this->assertSame(71.5, request_builder::to_number(' 71.5 '));
        $this->assertNull(request_builder::to_number(''));
        $this->assertFalse(request_builder::to_number('high'));
        $this->assertSame('3', request_builder::category_label(3.0));
        $this->assertNull(request_builder::category_key('  '));
        $this->assertSame('mid', request_builder::category_key(' Mid '));
        $this->assertSame(['a' => 'a', 'b c' => 'B c'], request_builder::split_codes('a, B c;A'));
    }

    /**
     * Unknown words in a message are left alone.
     */
    public function test_translate_leaves_other_text(): void {
        $req = new request();
        $req->labels = ['f1' => 'City', 'T1' => 'pair'];
        $this->assertSame('City and f10 and T1x and pair.', request_builder::translate($req, 'f1 and f10 and T1x and T1.'));
    }
}
