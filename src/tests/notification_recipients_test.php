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

namespace paygw_ifthenpay;

use paygw_ifthenpay\local\notification_recipients;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the resolution of staff notification recipients.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(notification_recipients::class)]
final class notification_recipients_test extends \advanced_testcase {
    /**
     * The gateway must be enabled: Moodle hides the message providers of disabled plugins.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        \core\plugininfo\paygw::enable_plugin('ifthenpay', 1);
    }

    /**
     * A user record's id as an int.
     *
     * @param \stdClass|false $user User record.
     * @return int
     */
    private static function id(\stdClass|false $user): int {
        return $user && is_numeric($user->id ?? null) ? (int) $user->id : 0;
    }

    /**
     * A course with an enrol_fee instance, and a transaction paying for it.
     *
     * @return array{0: \stdClass, 1: \stdClass} Course and transaction stub.
     */
    private function course_and_tx(): array {
        $course = $this->getDataGenerator()->create_course();
        /** @var \enrol_fee_plugin $plugin */
        $plugin = enrol_get_plugin('fee');
        $instanceid = $plugin->add_instance($course, ['cost' => 10, 'currency' => 'EUR', 'customint1' => 1]);
        $tx = (object) ['component' => 'enrol_fee', 'paymentarea' => 'fee', 'itemid' => $instanceid];
        return [$course, $tx];
    }

    /**
     * Someone who qualifies through several rules is listed once.
     *
     * @return void
     */
    public function test_admin_also_chosen_is_listed_once(): void {
        $this->resetAfterTest();
        [, $tx] = $this->course_and_tx();
        $adminid = self::id(get_admin());

        $config = ['notify_admins' => '1', 'notify_users' => [(string) $adminid, $adminid]];
        $recipients = notification_recipients::resolve($tx, $config, notification_recipients::PROVIDER_COMPLETED);

        $this->assertSame([$adminid], array_keys($recipients));
    }

    /**
     * The teachers rule yields the course's editors for enrol_fee, and nobody for another component.
     *
     * @return void
     */
    public function test_teachers_rule(): void {
        $this->resetAfterTest();
        [$course, $tx] = $this->course_and_tx();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->getDataGenerator()->create_and_enrol($course, 'student');

        $config = ['notify_teachers' => '1'];
        $this->assertSame(
            [self::id($teacher)],
            array_keys(notification_recipients::resolve($tx, $config, notification_recipients::PROVIDER_COMPLETED))
        );

        $other = (object) ['component' => 'mod_other', 'paymentarea' => 'x', 'itemid' => $tx->itemid];
        $this->assertSame([], notification_recipients::resolve($other, $config, notification_recipients::PROVIDER_COMPLETED));
    }

    /**
     * Suspended, deleted and nologin users are dropped.
     *
     * @return void
     */
    public function test_unavailable_users_dropped(): void {
        $this->resetAfterTest();
        [$course, $tx] = $this->course_and_tx();
        $gen = $this->getDataGenerator();
        $suspended = $gen->create_and_enrol($course, 'editingteacher', ['suspended' => 1]);
        $nologin = $gen->create_and_enrol($course, 'editingteacher', ['auth' => 'nologin']);
        $deleted = $gen->create_and_enrol($course, 'editingteacher');
        delete_user($deleted);
        $active = $gen->create_and_enrol($course, 'editingteacher');

        $config = ['notify_users' => [$suspended->id, $nologin->id, $deleted->id, $active->id]];
        $this->assertSame(
            [self::id($active)],
            array_keys(notification_recipients::resolve($tx, $config, notification_recipients::PROVIDER_PROBLEM))
        );
    }

    /**
     * Only holders of the capability can receive; a chosen user without it is dropped.
     *
     * @return void
     */
    public function test_capability_gates_receiving(): void {
        $this->resetAfterTest();
        [$course, $tx] = $this->course_and_tx();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $plain = $this->getDataGenerator()->create_user();

        $this->assertTrue(notification_recipients::can_receive(self::id($teacher), notification_recipients::PROVIDER_COMPLETED));
        $this->assertFalse(notification_recipients::can_receive(self::id($student), notification_recipients::PROVIDER_COMPLETED));
        $this->assertFalse(notification_recipients::can_receive(self::id($plain), notification_recipients::PROVIDER_PROBLEM));

        $config = ['notify_users' => [$plain->id, $teacher->id]];
        $this->assertSame(
            [self::id($teacher)],
            array_keys(notification_recipients::resolve($tx, $config, notification_recipients::PROVIDER_COMPLETED))
        );
    }

    /**
     * A provider disabled site-wide is received by nobody, not even an administrator.
     *
     * @return void
     */
    public function test_disabled_provider_received_by_nobody(): void {
        $this->resetAfterTest();
        [, $tx] = $this->course_and_tx();
        set_config('paygw_ifthenpay_paymentproblem_disable', 1, 'message');
        $adminid = self::id(get_admin());

        $this->assertFalse(notification_recipients::can_receive($adminid, notification_recipients::PROVIDER_PROBLEM));
        $this->assertTrue(notification_recipients::can_receive($adminid, notification_recipients::PROVIDER_COMPLETED));
        $this->assertSame(
            [],
            notification_recipients::resolve($tx, ['notify_admins' => '1'], notification_recipients::PROVIDER_PROBLEM)
        );
    }

    /**
     * Nothing configured means nobody, and malformed chosen ids are ignored.
     *
     * @return void
     */
    public function test_empty_and_malformed_config(): void {
        $this->resetAfterTest();
        [, $tx] = $this->course_and_tx();

        $this->assertSame([], notification_recipients::resolve($tx, [], notification_recipients::PROVIDER_COMPLETED));
        $this->assertSame([], notification_recipients::chosen_userids(['notify_users' => 'abc']));
        $this->assertSame([5], notification_recipients::chosen_userids(['notify_users' => ['5', 'x', '-3', 0, 5]]));
    }
}
