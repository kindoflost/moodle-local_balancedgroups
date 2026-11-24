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

namespace local_balancedgroups\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;

/**
 * Privacy provider for local_balancedgroups.
 *
 * The plugin stores no personal data of its own (groups it creates belong to core_group, and the
 * wizard state lives only in the session cache). It sends anonymised data to an external service,
 * which is declared here.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data sent to the Balanced Group Maker service.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('balancedgroupmaker', [
            'persontoken' => 'privacy:metadata:balancedgroupmaker:persontoken',
            'fieldvalues' => 'privacy:metadata:balancedgroupmaker:fieldvalues',
            'rulecodes' => 'privacy:metadata:balancedgroupmaker:rulecodes',
            'sitekey' => 'privacy:metadata:balancedgroupmaker:sitekey',
        ], 'privacy:metadata:balancedgroupmaker');
        $collection->add_subsystem_link('core_group', [], 'privacy:metadata:core_group');
        return $collection;
    }

    /**
     * No stored user data, so no contexts.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        return new contextlist();
    }

    /**
     * No stored user data, so no users.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist) {
    }

    /**
     * Nothing to export.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist) {
    }

    /**
     * Nothing to delete.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
    }

    /**
     * Nothing to delete.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
    }

    /**
     * Nothing to delete.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
    }
}
