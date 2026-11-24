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
 * A failed call to the Balanced Group Maker API, with a message meant for the teacher.
 *
 * @package    local_balancedgroups
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_exception extends \moodle_exception {
    /** @var int HTTP status (0 when no answer arrived). */
    public int $httpcode;

    /** @var string Plain-text message from the server, if any (English). */
    public string $servermessage;

    /** @var string[] Extra plain-text reasons from the server (errors and warnings). */
    public array $details;

    /**
     * Constructor.
     *
     * @param string $errorcode Language string in local_balancedgroups.
     * @param int $httpcode
     * @param mixed $a Placeholder value for the language string.
     * @param string $servermessage
     * @param string[] $details
     */
    public function __construct(
        string $errorcode,
        int $httpcode,
        $a = null,
        string $servermessage = '',
        array $details = []
    ) {
        $this->httpcode = $httpcode;
        $this->servermessage = $servermessage;
        $this->details = $details;
        parent::__construct($errorcode, 'local_balancedgroups', '', $a);
    }
}
