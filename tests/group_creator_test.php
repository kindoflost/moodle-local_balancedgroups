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

use local_balancedgroups\local\group_creator;

/**
 * Tests for creating the grouping and groups.
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\local\group_creator
 * @covers     \local_balancedgroups\event\groups_created
 */
final class group_creator_test extends \advanced_testcase {
    /**
     * Core-style naming schemes.
     */
    public function test_names(): void {
        $this->assertSame(['Group A', 'Group B', 'Group C'], group_creator::names('Group @', 3));
        $this->assertSame(['Team 1', 'Team 2'], group_creator::names('Team #', 2));
    }

    /**
     * Creates a grouping with the groups and members, and logs one event.
     */
    public function test_create(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $users = [];
        for ($i = 0; $i < 4; $i++) {
            $users[] = $gen->create_and_enrol($course, 'student')->id;
        }
        $sink = $this->redirectEvents();
        $groupingid = group_creator::create(
            $course,
            'Balanced',
            ['Group A', 'Group B'],
            [[$users[0], $users[2]], [$users[1], $users[3]]]
        );
        $events = array_filter(
            $sink->get_events(),
            static fn($e) => $e instanceof \local_balancedgroups\event\groups_created
        );
        $sink->close();

        $this->assertSame('Balanced', $DB->get_field('groupings', 'name', ['id' => $groupingid]));
        $groups = groups_get_all_groups($course->id, 0, $groupingid);
        $this->assertCount(2, $groups);
        $a = groups_get_group_by_name($course->id, 'Group A');
        $this->assertEqualsCanonicalizing([$users[0], $users[2]], array_keys(groups_get_members($a)));

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertSame($groupingid, (int)$event->objectid);
        $this->assertSame(['groups' => 2, 'members' => 4], $event->other);

        // Same names again: refused before anything is written.
        $this->assertNotEmpty(group_creator::check($course->id, 'Balanced', ['Group A']));
        $this->expectException(\moodle_exception::class);
        group_creator::create($course, 'Balanced', ['Group A', 'Group B'], [[$users[0]], [$users[1]]]);
    }

    /**
     * A member who left the course rolls back the whole creation.
     */
    public function test_create_rolls_back(): void {
        global $DB;
        $this->resetAfterTest();
        $this->preventResetByRollback(); // Tests run inside a transaction otherwise, which hides the rollback.
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $enrolled = $gen->create_and_enrol($course, 'student')->id;
        $outsider = $gen->create_user()->id;
        try {
            group_creator::create($course, 'G', ['Group A'], [[$enrolled, $outsider]]);
            $this->fail('Exception expected');
        } catch (\moodle_exception $e) {
            $this->assertSame('errornotenrolled', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists('groupings', ['courseid' => $course->id]));
        $this->assertFalse($DB->record_exists('groups', ['courseid' => $course->id]));
    }
}
