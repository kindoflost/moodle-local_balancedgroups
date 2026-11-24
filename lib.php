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
 * Callbacks for local_balancedgroups.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add "Balanced groups" to the course navigation (shown under the course "More" menu).
 *
 * The link appears when the site administrator has enabled the plugin, or for site
 * administrators so they can find it before enabling.
 *
 * @param navigation_node $navigation The course node.
 * @param stdClass $course
 * @param context $context Course context.
 */
function local_balancedgroups_extend_navigation_course(navigation_node $navigation, stdClass $course, context $context) {
    if ($course->id == SITEID || !has_capability('local/balancedgroups:use', $context)) {
        return;
    }
    if (!get_config('local_balancedgroups', 'enabled') && !has_capability('moodle/site:config', context_system::instance())) {
        return;
    }
    $url = new moodle_url('/local/balancedgroups/index.php', ['id' => $course->id]);
    $navigation->add(
        get_string('pluginname', 'local_balancedgroups'),
        $url,
        navigation_node::TYPE_SETTING,
        null,
        'local_balancedgroups',
        new pix_icon('i/group', '')
    );
}
