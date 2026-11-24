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
use context_system;
use stdClass;

/**
 * Reads the course data the teacher can balance on: people, profile fields and grades.
 *
 * Only fields the current user is allowed to see are offered.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source {
    /** @var string[] Custom profile field types that can be balanced. */
    private const PROFILE_TYPES = ['menu', 'checkbox', 'text'];

    /** @var stdClass */
    private stdClass $course;

    /** @var context_course */
    private context_course $context;

    /**
     * Constructor.
     *
     * @param stdClass $course
     */
    public function __construct(stdClass $course) {
        $this->course = $course;
        $this->context = context_course::instance($course->id);
    }

    /**
     * Fields offered for balancing.
     *
     * @return array Key => ['key', 'label', 'source' ('profile'|'customprofile'|'grade'), 'kind'].
     */
    public function fields(): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/user/profile/lib.php');
        $out = [];
        $add = static function (string $key, string $label, string $source, string $kind) use (&$out): void {
            $out[$key] = ['key' => $key, 'label' => $label, 'source' => $source, 'kind' => $kind];
        };

        // Standard profile fields.
        $hidden = array_filter(explode(',', $CFG->hiddenuserfields ?? ''));
        $seehidden = has_capability('moodle/course:viewhiddenuserfields', $this->context);
        foreach (['city', 'country'] as $f) {
            if ($seehidden || !in_array($f, $hidden, true)) {
                $add('std_' . $f, get_string($f), 'profile', request_builder::KIND_CATEGORY);
            }
        }
        $identity = \core_user\fields::get_identity_fields($this->context, false);
        foreach (['institution', 'department'] as $f) {
            if (in_array($f, $identity, true)) {
                $add('std_' . $f, get_string($f), 'profile', request_builder::KIND_CATEGORY);
            }
        }

        // Custom profile fields the current user may see.
        $viewall = has_capability('moodle/user:viewalldetails', context_system::instance());
        $viewidentity = has_capability('moodle/site:viewuseridentity', $this->context);
        $records = $DB->get_records('user_info_field', null, 'sortorder ASC');
        foreach ($records as $f) {
            if (!in_array($f->datatype, self::PROFILE_TYPES, true)) {
                continue;
            }
            $visible = (string)$f->visible;
            $ok = $visible === (string)PROFILE_VISIBLE_ALL
                || ($visible === (string)PROFILE_VISIBLE_TEACHERS && $viewidentity)
                || $viewall;
            if ($ok) {
                $add(
                    'pf_' . $f->id,
                    format_string($f->name, true, ['context' => context_system::instance()]),
                    'customprofile',
                    request_builder::KIND_CATEGORY
                );
            }
        }

        // Gradebook items, course total included.
        if (has_capability('moodle/grade:viewall', $this->context)) {
            require_once($CFG->libdir . '/gradelib.php');
            $items = \grade_item::fetch_all(['courseid' => $this->course->id]) ?: [];
            uasort($items, static fn($a, $b) => $a->sortorder <=> $b->sortorder);
            foreach ($items as $item) {
                if (!in_array((int)$item->gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE], true)) {
                    continue;
                }
                $label = $item->itemtype === 'course' ? get_string('coursetotal', 'grades') : $item->get_name(true);
                $add(
                    'gi_' . $item->id,
                    format_string($label, true, ['context' => $this->context]),
                    'grade',
                    request_builder::KIND_NUMBER
                );
            }
        }
        return $out;
    }

    /**
     * Roles that can be chosen as the people to group.
     *
     * @return array Role id => name (0 = all enrolled users).
     */
    public function roles(): array {
        $roles = role_fix_names(get_profile_roles($this->context), $this->context, ROLENAME_ALIAS, true);
        return [0 => get_string('allparticipants')] + $roles;
    }

    /**
     * Default role (the student archetype).
     *
     * @return int
     */
    public function default_roleid(): int {
        $roles = get_archetype_roles('student');
        $role = reset($roles);
        return $role ? (int)$role->id : 0;
    }

    /**
     * Existing groups of the course.
     *
     * @return array Group id => name (0 = no limit).
     */
    public function groups(): array {
        $out = [0 => get_string('nolimitgroup', 'local_balancedgroups')];
        foreach (groups_get_all_groups($this->course->id) as $g) {
            $out[$g->id] = format_string($g->name, true, ['context' => $this->context]);
        }
        return $out;
    }

    /**
     * People to group: active enrolments with the role, optionally only members of a group.
     *
     * @param int $roleid 0 for all enrolled users.
     * @param int $groupid 0 for no limit.
     * @return array User id => user record (with username, email, idnumber and name fields).
     */
    public function users(int $roleid, int $groupid): array {
        global $CFG;
        require_once($CFG->dirroot . '/group/lib.php');
        $sourcefilter = $groupid ? ['groupid' => $groupid] : null;
        return groups_get_potential_members(
            $this->course->id,
            $roleid ?: null,
            $sourcefilter,
            'lastname ASC, firstname ASC',
            null,
            true,
            ['email']
        );
    }

    /**
     * Values of the chosen Moodle fields (CSV columns are added by the caller).
     *
     * @param string[] $keys Field keys from fields().
     * @param int[] $userids
     * @return array User id => [field key => value or null].
     */
    public function values(array $keys, array $userids): array {
        global $CFG, $DB;
        $out = array_fill_keys($userids, []);
        if (!$userids) {
            return $out;
        }
        $allowed = $this->fields();
        $std = [];
        foreach ($keys as $key) {
            if (!isset($allowed[$key])) {
                continue;
            }
            if (strpos($key, 'std_') === 0) {
                $std[] = substr($key, 4);
            }
        }
        if ($std) {
            $countries = get_string_manager()->get_list_of_countries(true);
            $records = $DB->get_records_list('user', 'id', $userids, '', 'id, ' . implode(', ', $std));
            foreach ($records as $r) {
                foreach ($std as $f) {
                    $v = trim((string)$r->$f);
                    if ($f === 'country' && $v !== '') {
                        $v = $countries[$v] ?? $v;
                    }
                    $out[$r->id]['std_' . $f] = $v === '' ? null : $v;
                }
            }
        }
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u');
        $regraded = false;
        foreach ($keys as $key) {
            if (!isset($allowed[$key])) {
                continue;
            }
            if (strpos($key, 'pf_') === 0) {
                $fieldid = (int)substr($key, 3);
                $datatype = $DB->get_field('user_info_field', 'datatype', ['id' => $fieldid]);
                $records = $DB->get_records_select(
                    'user_info_data',
                    "fieldid = :fieldid AND userid $insql",
                    ['fieldid' => $fieldid] + $inparams,
                    '',
                    'userid, data'
                );
                foreach ($records as $r) {
                    $v = trim((string)$r->data);
                    if ($datatype === 'checkbox') {
                        $v = $v === '1' ? get_string('yes') : get_string('no');
                    }
                    $out[$r->userid][$key] = $v === '' ? null : $v;
                }
            } else if (strpos($key, 'gi_') === 0) {
                require_once($CFG->libdir . '/gradelib.php');
                if (!$regraded) {
                    grade_regrade_final_grades_if_required($this->course);
                    $regraded = true;
                }
                $itemid = (int)substr($key, 3);
                $records = $DB->get_records_select(
                    'grade_grades',
                    "itemid = :itemid AND userid $insql",
                    ['itemid' => $itemid] + $inparams,
                    '',
                    'userid, finalgrade'
                );
                foreach ($records as $r) {
                    $out[$r->userid][$key] = $r->finalgrade === null ? null : (float)$r->finalgrade;
                }
            }
        }
        return $out;
    }
}
