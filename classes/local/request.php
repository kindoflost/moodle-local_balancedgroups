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

/**
 * One anonymised request to the Balanced Group Maker API, plus the keys to read its answer.
 *
 * Everything that maps the random tokens back to Moodle users lives only in this object
 * (kept in the user's session); none of it is sent.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class request {
    /** @var array JSON body sent to the API. */
    public array $payload = [];

    /** @var array Random person token => Moodle user id. */
    public array $tokens = [];

    /** @var array Field code (f1, f2...) => field key used in this plugin. */
    public array $fieldcodes = [];

    /** @var array Any code that was sent (person token, field code, category or rule code) => readable label. */
    public array $labels = [];

    /** @var array Field key => [category code => category label]. */
    public array $categories = [];
}
