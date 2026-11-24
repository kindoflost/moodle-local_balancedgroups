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
use core_privacy\local\metadata\types\external_location;

/**
 * Privacy provider tests.
 *
 * @package    local_balancedgroups
 * @category   test
 * @copyright  2026 Kind of Lost <mario@kindoflost.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_balancedgroups\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The external service is declared, and no user data is stored.
     */
    public function test_metadata_and_no_data(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_balancedgroups'));
        $external = array_values(array_filter(
            $collection->get_collection(),
            static fn($item) => $item instanceof external_location
        ));
        $this->assertCount(1, $external);
        $this->assertSame('balancedgroupmaker', $external[0]->get_name());
        $this->assertArrayHasKey('persontoken', $external[0]->get_privacy_fields());

        $user = $this->getDataGenerator()->create_user();
        $this->assertEmpty(provider::get_contexts_for_userid($user->id)->get_contextids());
    }
}
