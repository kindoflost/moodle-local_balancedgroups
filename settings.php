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

/**
 * Admin settings for local_balancedgroups.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_balancedgroups', get_string('pluginname', 'local_balancedgroups'));
    $ADMIN->add('localplugins', $settings);

    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_heading(
            'local_balancedgroups/disclosure',
            '',
            get_string('settingsdisclosure', 'local_balancedgroups')
        ));

        $settings->add(new admin_setting_configcheckbox(
            'local_balancedgroups/enabled',
            get_string('enabled', 'local_balancedgroups'),
            get_string('enabled_desc', 'local_balancedgroups'),
            0
        ));

        $settings->add(new admin_setting_configtext(
            'local_balancedgroups/apiurl',
            get_string('apiurl', 'local_balancedgroups'),
            get_string('apiurl_desc', 'local_balancedgroups'),
            \local_balancedgroups\local\api_client::DEFAULT_URL,
            PARAM_URL,
            60
        ));

        $settings->add(new admin_setting_configtext(
            'local_balancedgroups/timeout',
            get_string('timeout', 'local_balancedgroups'),
            get_string('timeout_desc', 'local_balancedgroups'),
            \local_balancedgroups\local\api_client::DEFAULT_TIMEOUT,
            PARAM_INT,
            6
        ));
    }
}
