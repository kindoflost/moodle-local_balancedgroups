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

namespace local_balancedgroups\event;

/**
 * Event fired when balanced groups are created in a course.
 *
 * @property-read array $other {
 *      - int groups: number of groups created
 *      - int members: number of memberships added
 * }
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class groups_created extends \core\event\base {
    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'groupings';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventgroupscreated', 'local_balancedgroups');
    }

    /**
     * Non-localised description.
     *
     * @return string
     */
    public function get_description() {
        $groups = (int)($this->other['groups'] ?? 0);
        $members = (int)($this->other['members'] ?? 0);
        return "The user with id '$this->userid' created $groups balanced groups ($members members) " .
            "in the grouping with id '$this->objectid' in the course with id '$this->courseid'.";
    }

    /**
     * Where to see the result.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/group/groupings.php', ['id' => $this->courseid]);
    }

    /**
     * Validate the data.
     *
     * @throws \coding_exception
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->other['groups']) || !isset($this->other['members'])) {
            throw new \coding_exception('The \'groups\' and \'members\' values must be set in other.');
        }
    }

    /**
     * Backup/restore mapping of the object id.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'groupings', 'restore' => 'grouping'];
    }

    /**
     * Backup/restore mapping of other.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
