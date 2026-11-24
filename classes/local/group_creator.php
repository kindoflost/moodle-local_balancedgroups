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

use context_course;
use moodle_exception;

/**
 * Creates the grouping and its groups with the core groups API.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class group_creator {
    /**
     * Group names from a core-style naming scheme ("Group @" gives A, B, C...; "Group #" gives 1, 2, 3...).
     *
     * @param string $scheme
     * @param int $count
     * @return string[]
     */
    public static function names(string $scheme, int $count): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = trim(groups_parse_name($scheme, $i));
        }
        return $out;
    }

    /**
     * Problems that would stop creation (names already used in the course, duplicate names).
     *
     * @param int $courseid
     * @param string $groupingname
     * @param string[] $groupnames
     * @return string[] Messages; empty when creation can go ahead.
     */
    public static function check(int $courseid, string $groupingname, array $groupnames): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $problems = [];
        if (trim($groupingname) === '') {
            $problems[] = get_string('errorgroupingnameempty', 'local_balancedgroups');
        } else if (groups_get_grouping_by_name($courseid, $groupingname)) {
            $problems[] = get_string('errorgroupingexists', 'local_balancedgroups', s($groupingname));
        }
        if (count(array_unique($groupnames)) !== count($groupnames)) {
            $problems[] = get_string('errornamesnotunique', 'local_balancedgroups');
        }
        foreach ($groupnames as $name) {
            if (groups_get_group_by_name($courseid, $name)) {
                $problems[] = get_string('errorgroupexists', 'local_balancedgroups', s($name));
            }
        }
        return $problems;
    }

    /**
     * Create a grouping with one group per list of users, in one transaction, and log it.
     *
     * @param \stdClass $course
     * @param string $groupingname
     * @param string[] $groupnames
     * @param array $groups List of lists of user ids, same order as $groupnames.
     * @return int Grouping id.
     * @throws moodle_exception When a name is taken or a member is no longer enrolled.
     */
    public static function create(\stdClass $course, string $groupingname, array $groupnames, array $groups): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/group/lib.php');
        $context = context_course::instance($course->id);
        $problems = self::check($course->id, $groupingname, $groupnames);
        if ($problems) {
            throw new moodle_exception('errorcannotcreate', 'local_balancedgroups', '', implode(' ', $problems));
        }
        $members = 0;
        $transaction = $DB->start_delegated_transaction();
        try {
            $groupingid = groups_create_grouping((object)[
                'courseid' => $course->id,
                'name' => $groupingname,
                'description' => get_string('groupingdescription', 'local_balancedgroups'),
                'descriptionformat' => FORMAT_HTML,
            ]);
            foreach ($groups as $i => $userids) {
                $groupid = groups_create_group((object)['courseid' => $course->id, 'name' => $groupnames[$i]]);
                groups_assign_grouping($groupingid, $groupid);
                foreach ($userids as $userid) {
                    if (!groups_add_member($groupid, $userid)) {
                        throw new moodle_exception(
                            'errornotenrolled',
                            'local_balancedgroups',
                            '',
                            fullname(\core_user::get_user($userid))
                        );
                    }
                    $members++;
                }
            }
            $transaction->allow_commit();
        } catch (\Throwable $e) {
            // Nothing is kept unless every group and member was written.
            $transaction->rollback($e);
        }

        \local_balancedgroups\event\groups_created::create([
            'context' => $context,
            'objectid' => $groupingid,
            'other' => ['groups' => count($groups), 'members' => $members],
        ])->trigger();
        return $groupingid;
    }
}
