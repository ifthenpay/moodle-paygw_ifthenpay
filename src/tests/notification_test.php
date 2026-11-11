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

defined('MOODLE_INTERNAL') || die();

use paygw_ifthenpay\event\notification_failed;
use paygw_ifthenpay\task\send_notification;
use PHPUnit\Framework\Attributes\CoversFunction;

require_once(__DIR__ . '/../lib.php');

/**
 * Tests for queueing staff notifications from the webhook.
 *
 * Deliveries go through a real enrol_fee instance (deliver_order() enrols the payer). enrol_fee
 * never refuses a delivery, so the "delivery refused" case uses a stand-in component whose
 * service provider returns false: core_payment\helper::deliver_order() resolves the provider by
 * class name ("<component>\payment\service_provider", via class_exists), so the test registers an
 * anonymous provider under such a name with class_alias().
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('paygw_ifthenpay_notify')]
#[CoversFunction('paygw_ifthenpay_process_webhook')]
final class notification_test extends \advanced_testcase {
    /** @var string Gateway key of the test transactions. */
    private const KEY = 'GKEY-123';

    /** @var string Component whose deliveries are always refused. */
    private const REFUSING = 'paygw_ifthenpay_refusing';

    /**
     * The gateway must be enabled: Moodle hides the message providers of disabled plugins.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * A PENDING transaction for an enrol_fee course (or a refusing component), on an ifthenpay account.
     *
     * @param array<string, mixed>|null $config Gateway config to store, or null to leave the default.
     * @param bool $refusing Use the component whose delivery is refused.
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} Transaction, course, editing teacher.
     */
    private function setup_tx(?array $config, bool $refusing = false): array {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $payer = $gen->create_user();
        /** @var \core_payment_generator $pgen */
        $pgen = $gen->get_plugin_generator('core_payment');
        $account = $pgen->create_payment_account(['gateways' => 'ifthenpay']);
        $accountid = self::int($account->get('id'));
        if ($config !== null) {
            $db->set_field('payment_gateways', 'config', json_encode($config), ['accountid' => $accountid, 'gateway' => 'ifthenpay']);
        }
        /** @var \enrol_fee_plugin $plugin */
        $plugin = enrol_get_plugin('fee');
        $instanceid = $plugin->add_instance($course, ['cost' => 10, 'currency' => 'EUR', 'customint1' => $accountid]);
        if ($refusing) {
            $this->register_refusing_provider();
        }

        $token = 'tok-' . random_string(10);
        $txid = $db->insert_record('paygw_ifthenpay_tx', (object) [
            'timecreated' => time(),
            'timemodified' => time(),
            'token' => $token,
            'userid' => $payer->id,
            'component' => $refusing ? self::REFUSING : 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid' => $instanceid,
            'accountid' => $accountid,
            'amount' => '10.00',
            'currency' => 'EUR',
            'gateway_key' => self::KEY,
            'state' => 'PENDING',
        ]);
        /** @var \stdClass $tx */
        $tx = $db->get_record('paygw_ifthenpay_tx', ['id' => $txid], '*', MUST_EXIST);
        return [$tx, $course, $teacher];
    }

    /**
     * Register a payment service provider that refuses every delivery.
     *
     * @return void
     */
    private function register_refusing_provider(): void {
        $name = self::REFUSING . '\\payment\\service_provider';
        if (class_exists($name)) {
            return;
        }
        $provider = new class implements \core_payment\local\callback\service_provider {
            /**
             * Not used by the webhook.
             *
             * @param string $paymentarea Payment area.
             * @param int $itemid Item id.
             * @return \core_payment\local\entities\payable
             */
            public static function get_payable(string $paymentarea, int $itemid): \core_payment\local\entities\payable {
                return new \core_payment\local\entities\payable(10, 'EUR', 0);
            }

            /**
             * Not used by the webhook.
             *
             * @param string $paymentarea Payment area.
             * @param int $itemid Item id.
             * @return \moodle_url
             */
            public static function get_success_url(string $paymentarea, int $itemid): \moodle_url {
                return new \moodle_url('/');
            }

            /**
             * Always refuses.
             *
             * @param string $paymentarea Payment area.
             * @param int $itemid Item id.
             * @param int $paymentid Payment id.
             * @param int $userid User id.
             * @return bool
             */
            public static function deliver_order(string $paymentarea, int $itemid, int $paymentid, int $userid): bool {
                return false;
            }
        };
        class_alias(get_class($provider), $name);
    }

    /**
     * Call the webhook for a transaction; debugging from problem reports is expected and cleared.
     *
     * @param \stdClass $tx Transaction.
     * @param string $amount Notified amount.
     * @return string Webhook outcome.
     */
    private function webhook(\stdClass $tx, string $amount = '10.00'): string {
        $result = paygw_ifthenpay_process_webhook(self::str($tx->token), $amount, base64_encode(self::KEY));
        $this->resetDebugging();
        return $result;
    }

    /**
     * Queued notification tasks' custom data.
     *
     * @return array<int, \stdClass>
     */
    private static function queued(): array {
        $out = [];
        /** @var \core\task\adhoc_task[] $tasks */
        $tasks = \core\task\manager::get_adhoc_tasks('\\' . send_notification::class);
        foreach ($tasks as $task) {
            $data = $task->get_custom_data();
            if ($data instanceof \stdClass) {
                $out[] = $data;
            }
        }
        return $out;
    }

    /**
     * Recipient ids of the queued tasks for an event (and problem type), sorted.
     *
     * @param string $event 'completed' or 'problem'.
     * @param string|null $type Problem type.
     * @return int[]
     */
    private static function recipients(string $event, ?string $type = null): array {
        $ids = [];
        foreach (self::queued() as $data) {
            if ($data->event === $event && ($type === null || ($data->problemtype ?? null) === $type)) {
                $ids[] = is_numeric($data->recipientid ?? null) ? (int) $data->recipientid : 0;
            }
        }
        sort($ids);
        return $ids;
    }

    /**
     * The notifiedproblems bitmask of a transaction.
     *
     * @param \stdClass $tx Transaction.
     * @return int
     */
    private static function bits(\stdClass $tx): int {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        $value = $db->get_field('paygw_ifthenpay_tx', 'notifiedproblems', ['id' => $tx->id]);
        return is_numeric($value) ? (int) $value : -1;
    }

    /**
     * Configuration with every event and recipient rule on.
     *
     * @return array<string, mixed>
     */
    private static function all_on(): array {
        return [
            'notify_completed' => '1',
            'notify_problems' => '1',
            'notify_admins' => '1',
            'notify_teachers' => '1',
            'notify_users' => [(string) self::id(get_admin())],
        ];
    }

    /**
     * A successful webhook queues one task per unique recipient; a repeat queues nothing more.
     *
     * @return void
     */
    public function test_success_once_per_unique_recipient(): void {
        [$tx, $course, $teacher] = $this->setup_tx(self::all_on());

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($tx));
        $expected = [self::id(get_admin()), self::id($teacher)];
        sort($expected);
        $this->assertSame($expected, self::recipients('completed'));
        $this->assertTrue(self::enrolled($course, $tx));
        foreach (self::queued() as $data) {
            $this->assertEquals($tx->id, $data->txid);
            $this->assertObjectNotHasProperty('reason', $data);
        }

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($tx));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($tx));
        $this->assertCount(2, self::queued());
    }

    /**
     * Delivery refused three times: no completed notification, one problem notification.
     *
     * @return void
     */
    public function test_delivery_refused_notified_once(): void {
        [$tx] = $this->setup_tx(['notify_completed' => '1', 'notify_problems' => '1', 'notify_admins' => '1'], true);

        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_RETRY, $this->webhook($tx));
        }
        $this->assertSame([], self::recipients('completed'));
        $this->assertSame([self::id(get_admin())], self::recipients('problem', 'delivery'));
        $this->assertCount(1, self::queued());
        $this->assertSame(PAYGW_IFTHENPAY_PROBLEM_DELIVERY, self::bits($tx));
    }

    /**
     * A repeated amount mismatch is notified once; so is a mismatch on an already-PAID transaction.
     *
     * @return void
     */
    public function test_mismatch_notified_once(): void {
        $config = ['notify_completed' => '1', 'notify_problems' => '1', 'notify_admins' => '1'];
        $adminid = self::id(get_admin());

        [$pending] = $this->setup_tx($config);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($pending, '9.99'));
        }
        $this->assertSame([$adminid], self::recipients('problem', 'mismatch'));
        $this->assertSame(PAYGW_IFTHENPAY_PROBLEM_MISMATCH, self::bits($pending));

        [$paid] = $this->setup_tx($config);
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($paid));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($paid, '1.00'));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($paid, '2.00'));
        $this->assertSame([$adminid, $adminid], self::recipients('problem', 'mismatch'));
        $this->assertSame([$adminid], self::recipients('completed'));
        $this->assertSame(PAYGW_IFTHENPAY_PROBLEM_MISMATCH, self::bits($paid));
    }

    /**
     * A wrong anti-phishing key is notified once per transaction, as 'badkey'.
     *
     * @return void
     */
    public function test_badkey_notified_once(): void {
        [$tx] = $this->setup_tx(['notify_problems' => '1', 'notify_admins' => '1']);
        for ($i = 0; $i < 2; $i++) {
            $result = paygw_ifthenpay_process_webhook(self::str($tx->token), '10.00', base64_encode('WRONG'));
            $this->resetDebugging();
            $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_BADKEY, $result);
        }
        $this->assertSame([self::id(get_admin())], self::recipients('problem', 'badkey'));
        $this->assertCount(1, self::queued());
        $this->assertSame(PAYGW_IFTHENPAY_PROBLEM_BADKEY, self::bits($tx));
    }

    /**
     * With no notification settings (the default, also after upgrade) nothing is queued and no bit set.
     *
     * @return void
     */
    public function test_no_config_nothing_queued(): void {
        [$tx] = $this->setup_tx(null);
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($tx, '9.99'));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($tx));
        $this->assertSame([], self::queued());
        $this->assertSame(0, self::bits($tx));

        // Rules on but the event off: same.
        [$tx2] = $this->setup_tx(['notify_admins' => '1']);
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($tx2, '9.99'));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $this->webhook($tx2));
        $this->assertSame([], self::queued());
        $this->assertSame(0, self::bits($tx2));
    }

    /**
     * Make the ad-hoc task queue fail: queue_adhoc_task() reads the clock first.
     *
     * @return void
     */
    private static function break_task_queue(): void {
        \core\di::set(\core\clock::class, new class implements \core\clock {
            /**
             * Always fails.
             *
             * @return \DateTimeImmutable
             */
            public function now(): \DateTimeImmutable {
                throw new \RuntimeException('clock broken for the test');
            }

            /**
             * Always fails.
             *
             * @return int
             */
            public function time(): int {
                throw new \RuntimeException('clock broken for the test');
            }
        });
    }

    /**
     * A queueing failure leaves the bit unset, the webhook result unchanged, and is logged.
     *
     * @return void
     */
    public function test_queueing_failure_rolls_back(): void {
        [$tx] = $this->setup_tx(self::all_on());
        $events = $this->redirectEvents();
        self::break_task_queue();
        try {
            $result = paygw_ifthenpay_process_webhook(self::str($tx->token), '9.99', base64_encode(self::KEY));
            /** @var \stdClass[] $messages */
            $messages = $this->getDebuggingMessages();
            $this->resetDebugging();
        } finally {
            \core\di::set(\core\clock::class, new \core\system_clock());
        }

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $result);
        $this->assertSame(0, self::bits($tx));
        $this->assertSame([], self::queued());
        $this->assertNotEmpty(array_filter($messages, fn($m) => str_contains(self::str($m->message ?? null), 'not queued')));
        $failed = array_values(array_filter($events->get_events(), fn($e) => $e instanceof notification_failed));
        $this->assertCount(1, $failed);
        $this->assertEmpty($failed[0]->relateduserid);
        $this->assertIsArray($failed[0]->other);
        $this->assertSame('mismatch', $failed[0]->other['problemtype']);

        // Once the queue works again, the problem is still notified (the bit was not burnt).
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_MISMATCH, $this->webhook($tx, '9.99'));
        $this->assertNotEmpty(self::recipients('problem', 'mismatch'));
    }

    /**
     * A failure inside the notification step after a successful payment still answers OK, with the
     * payment settled and the payer enrolled.
     *
     * @return void
     */
    public function test_failure_in_notify_keeps_ok(): void {
        [$tx, $course] = $this->setup_tx(self::all_on());
        self::break_task_queue();
        try {
            $result = paygw_ifthenpay_process_webhook(self::str($tx->token), '10.00', base64_encode(self::KEY));
            $this->resetDebugging();
        } finally {
            \core\di::set(\core\clock::class, new \core\system_clock());
        }

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_OK, $result);
        $this->assertSame('PAID', paygw_ifthenpay_tx_get(self::str($tx->token))?->state);
        $this->assertTrue(self::enrolled($course, $tx));
        $this->assertSame([], self::queued());
    }

    /**
     * An unexpected error on a known transaction is notified once as 'error', with a truncated reason.
     *
     * @return void
     */
    public function test_error_notified_with_reason(): void {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        [$tx] = $this->setup_tx(['notify_problems' => '1', 'notify_admins' => '1']);
        // A missing enrolment instance makes enrol_fee's deliver_order() throw.
        $db->delete_records('enrol', ['id' => $tx->itemid]);

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_RETRY, $this->webhook($tx));
        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_RETRY, $this->webhook($tx));
        $queued = self::queued();
        $this->assertCount(1, $queued);
        $this->assertSame('error', $queued[0]->problemtype);
        $this->assertIsString($queued[0]->reason);
        $this->assertNotSame('', $queued[0]->reason);
        $this->assertLessThanOrEqual(send_notification::REASON_MAX_LENGTH, \core_text::strlen($queued[0]->reason));
        $this->assertSame(PAYGW_IFTHENPAY_PROBLEM_ERROR, self::bits($tx));
    }

    /**
     * A webhook for an unknown reference queues nothing.
     *
     * @return void
     */
    public function test_unknown_reference_nothing(): void {
        [$tx] = $this->setup_tx(self::all_on());
        $result = paygw_ifthenpay_process_webhook('no-such-token', '10.00', base64_encode(self::KEY));
        $this->resetDebugging();

        $this->assertSame(PAYGW_IFTHENPAY_WEBHOOK_UNKNOWN, $result);
        $this->assertSame([], self::queued());
        $this->assertSame(0, self::bits($tx));
    }

    /**
     * A scalar value as a string; anything else as ''.
     *
     * @param mixed $value Value.
     * @return string
     */
    private static function str(mixed $value): string {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * A numeric value as an int; anything else as 0.
     *
     * @param mixed $value Value.
     * @return int
     */
    private static function int(mixed $value): int {
        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * Whether the transaction's payer is enrolled in the course.
     *
     * @param \stdClass $course Course.
     * @param \stdClass $tx Transaction.
     * @return bool
     */
    private static function enrolled(\stdClass $course, \stdClass $tx): bool {
        /** @var \context_course $context */
        $context = \context_course::instance(self::int($course->id), MUST_EXIST);
        return is_enrolled($context, self::int($tx->userid));
    }
}
