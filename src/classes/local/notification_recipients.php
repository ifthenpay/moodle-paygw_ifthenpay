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

namespace paygw_ifthenpay\local;

use stdClass;

/**
 * Who receives the staff payment notifications for a transaction.
 *
 * The rules come from the payment account's gateway configuration (notify_admins,
 * notify_teachers, notify_users). The result is keyed by user id, which is what makes a person
 * who qualifies through several rules receive a single notification.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_recipients {
    /** @var string Provider of the completed-payment notification. */
    public const PROVIDER_COMPLETED = 'paymentcompleted';

    /** @var string Provider of the payment-problem notification. */
    public const PROVIDER_PROBLEM = 'paymentproblem';

    /**
     * Whether Moodle would deliver this plugin's provider to the user.
     *
     * A provider disabled site-wide reaches nobody; otherwise this is holds_capability().
     *
     * @param int $userid User id.
     * @param string $provider One of the PROVIDER_* constants.
     * @return bool
     */
    public static function can_receive(int $userid, string $provider): bool {
        // An administrator who disables a provider site-wide has chosen silence: nobody receives it,
        // and message_send() would refuse it anyway. Treated as "cannot receive" so nothing is
        // queued and no failure is logged for a deliberate setting.
        if (get_config('message', 'paygw_ifthenpay_' . $provider . '_disable')) {
            return false;
        }
        return self::holds_capability($userid);
    }

    /**
     * Whether the user holds the capability this plugin's providers require.
     *
     * Ignores any site-wide disable, so it answers "is this user eligible at all", which is what
     * the configuration form must check: a provider an administrator turned off for now does not
     * make a chosen user wrong. Both providers share paygw/ifthenpay:receivenotifications.
     *
     * message_send() refuses a provider the recipient cannot use (its capability is checked in
     * any context), so this asks the same question the same way, through
     * message_get_providers_for_user(), rather than approximating it with has_capability() in one
     * context.
     *
     * @param int $userid User id.
     * @return bool
     */
    public static function holds_capability(int $userid): bool {
        /** @var \stdClass[] $providers */
        $providers = message_get_providers_for_user($userid);
        foreach ($providers as $p) {
            if ($p->component === 'paygw_ifthenpay' && $p->name === self::PROVIDER_COMPLETED) {
                return true;
            }
        }
        return false;
    }

    /**
     * The chosen user ids stored in a gateway configuration, cleaned.
     *
     * @param array<string, mixed> $config Decoded gateway configuration.
     * @return int[] Positive user ids, without duplicates.
     */
    public static function chosen_userids(array $config): array {
        $raw = $config['notify_users'] ?? [];
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[(int) $id] = (int) $id;
            }
        }
        return array_values($ids);
    }

    /**
     * Resolve the recipients of one notification.
     *
     * @param stdClass $tx Transaction record (component, paymentarea, itemid are used).
     * @param array<string, mixed> $config Decoded gateway configuration of the tx's account.
     * @param string $provider One of the PROVIDER_* constants.
     * @return array<int, stdClass> Full user records keyed by id.
     */
    public static function resolve(stdClass $tx, array $config, string $provider): array {
        global $DB;

        $ids = [];
        if (!empty($config['notify_admins'])) {
            /** @var array<int, stdClass> $admins */
            $admins = get_admins();
            foreach (array_keys($admins) as $id) {
                $ids[(int) $id] = true;
            }
        }
        if (!empty($config['notify_teachers'])) {
            $courseid = self::courseid($tx);
            if ($courseid) {
                /** @var \context_course $context */
                $context = \context_course::instance($courseid);
                /** @var array<int, stdClass> $editors */
                $editors = get_users_by_capability($context, 'moodle/course:update', 'u.id');
                foreach (array_keys($editors) as $id) {
                    $ids[(int) $id] = true;
                }
            }
        }
        foreach (self::chosen_userids($config) as $id) {
            $ids[$id] = true;
        }
        if (!$ids) {
            return [];
        }

        $recipients = [];
        /** @var \moodle_database $db */
        $db = $DB;
        /** @var array<int, stdClass> $users */
        $users = $db->get_records_list('user', 'id', array_keys($ids));
        foreach ($users as $id => $user) {
            if (!empty($user->deleted) || !empty($user->suspended) || ($user->auth ?? '') === 'nologin') {
                continue;
            }
            if (!self::can_receive((int) $id, $provider)) {
                continue;
            }
            $recipients[(int) $id] = $user;
        }
        return $recipients;
    }

    /**
     * The course a transaction's item belongs to, when it has one.
     *
     * Only enrol_fee (the one core component that takes payments) maps an item to a course: its
     * item id is the enrolment instance. Any other component yields none, and the teachers rule
     * then adds nobody.
     *
     * @param stdClass $tx Transaction record.
     * @return int|null Course id.
     */
    public static function courseid(stdClass $tx): ?int {
        global $DB;

        if (($tx->component ?? null) !== 'enrol_fee' || !is_numeric($tx->itemid ?? null)) {
            return null;
        }
        /** @var \moodle_database $db */
        $db = $DB;
        $courseid = $db->get_field('enrol', 'courseid', ['enrol' => 'fee', 'id' => (int) $tx->itemid]);
        return is_numeric($courseid) ? (int) $courseid : null;
    }
}
