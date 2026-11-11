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

namespace paygw_ifthenpay\task;

use paygw_ifthenpay\event\notification_failed;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the ad-hoc task that sends one staff payment notification.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(send_notification::class)]
#[CoversClass(notification_failed::class)]
final class send_notification_test extends \advanced_testcase {
    /** @var string Gateway key of the test transaction; must never appear in a message. */
    private const GATEWAY_KEY = 'SECRET-KEY-1234';

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
     * A course sold through enrol_fee, a payer and a transaction for it.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass} Course, payer and transaction records.
     */
    private function course_payer_tx(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['fullname' => 'Advanced Widgets']);
        $payer = $gen->create_user(['firstname' => 'Paula', 'lastname' => 'Payer']);
        /** @var \core_payment_generator $pgen */
        $pgen = $gen->get_plugin_generator('core_payment');
        $account = $pgen->create_payment_account(['gateways' => 'ifthenpay']);
        /** @var \enrol_fee_plugin $plugin */
        $plugin = enrol_get_plugin('fee');
        $instanceid = $plugin->add_instance($course, ['cost' => 10.5, 'currency' => 'EUR', 'customint1' => $account->get('id')]);

        /** @var \moodle_database $db */
        $db = $DB;
        $txid = $db->insert_record('paygw_ifthenpay_tx', (object) [
            'timecreated' => time(),
            'timemodified' => time(),
            'token' => 'tok-abc-123',
            'userid' => $payer->id,
            'component' => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid' => $instanceid,
            'accountid' => $account->get('id'),
            'amount' => '10.50',
            'currency' => 'EUR',
            'gateway_key' => self::GATEWAY_KEY,
            'state' => 'PAID',
        ]);
        /** @var \stdClass $tx */
        $tx = $db->get_record('paygw_ifthenpay_tx', ['id' => $txid], '*', MUST_EXIST);
        return [$course, $payer, $tx];
    }

    /**
     * Run the task with the given custom data.
     *
     * @param array<string, mixed> $data Custom data.
     * @return void
     */
    private function run_task(array $data, ?send_notification $task = null): void {
        $task = $task ?? new send_notification();
        $task->set_custom_data($data);
        $task->execute();
    }

    /**
     * The only message caught by the sink.
     *
     * @param \phpunit_message_sink $sink Message sink.
     * @return \stdClass
     */
    private function single_message(\phpunit_message_sink $sink): \stdClass {
        /** @var \stdClass[] $messages */
        $messages = $sink->get_messages();
        $this->assertCount(1, $messages);
        $message = reset($messages);
        $this->assertInstanceOf(\stdClass::class, $message);
        return $message;
    }

    /**
     * A completed-payment notification names the payer, course, amount, reference and date, with links.
     *
     * @return void
     */
    public function test_completed_content(): void {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        [$course, $payer, $tx] = $this->course_payer_tx();
        $admin = get_admin();
        $this->assertInstanceOf(\stdClass::class, $admin);
        $db->set_field('user', 'timezone', 'Asia/Tokyo', ['id' => $admin->id]);
        $time = 1767225600;

        $sink = $this->redirectMessages();
        $this->run_task(['txid' => $tx->id, 'event' => 'completed', 'recipientid' => $admin->id, 'time' => $time]);
        $msg = $this->single_message($sink);

        $this->assertSame('paygw_ifthenpay', $msg->component);
        $this->assertSame('paymentcompleted', $msg->eventtype);
        $this->assertEquals(self::id($admin), $msg->useridto);
        $this->assertStringContainsString('Advanced Widgets', self::str($msg->subject));
        $amount = \core_payment\helper::get_cost_as_string(10.5, 'EUR');
        $date = userdate($time, get_string('strftimedatetime', 'langconfig'), 'Asia/Tokyo');
        $profileurl = (new \moodle_url('/user/profile.php', ['id' => $payer->id]))->out(false);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        foreach (['Paula Payer', 'Advanced Widgets', $amount, 'tok-abc-123', $date, $profileurl, $courseurl] as $expected) {
            $this->assertStringContainsString($expected, self::str($msg->fullmessage));
        }
        $html = self::str($msg->fullmessagehtml);
        $this->assertStringContainsString('href="' . s($profileurl) . '"', $html);
        $this->assertStringContainsString('href="' . s($courseurl) . '"', $html);
        $this->assertStringContainsString('tok-abc-123', $html);
        $this->assertSame($courseurl, $msg->contexturl);
        $this->assertSame('Advanced Widgets', $msg->contexturlname);
        $this->assertStringNotContainsString(self::GATEWAY_KEY, self::str($msg->fullmessage) . $html . self::str($msg->subject));
    }

    /**
     * Each problem type gets its localised description; an error's detail is truncated; no key leaks.
     *
     * @return void
     */
    public function test_problem_content(): void {
        [, , $tx] = $this->course_payer_tx();
        $adminid = self::id(get_admin());

        $sink = $this->redirectMessages();
        foreach (['badkey', 'mismatch', 'delivery'] as $type) {
            $this->run_task(['txid' => $tx->id, 'event' => 'problem', 'problemtype' => $type, 'recipientid' => $adminid,
                'time' => time()]);
        }
        $this->run_task(['txid' => $tx->id, 'event' => 'problem', 'problemtype' => 'error', 'recipientid' => $adminid,
            'time' => time(), 'reason' => str_repeat('x', 300)]);

        /** @var \stdClass[] $messages */
        $messages = array_values($sink->get_messages());
        $this->assertCount(4, $messages);
        foreach (['badkey', 'mismatch', 'delivery', 'error'] as $i => $type) {
            $this->assertSame('paymentproblem', $messages[$i]->eventtype);
            $this->assertStringContainsString(
                get_string('notification:problem_' . $type, 'paygw_ifthenpay'),
                self::str($messages[$i]->fullmessage)
            );
            $this->assertStringContainsString('tok-abc-123', self::str($messages[$i]->fullmessage));
            $this->assertStringNotContainsString(self::GATEWAY_KEY, self::str($messages[$i]->fullmessage));
        }
        $error = self::str($messages[3]->fullmessage);
        $this->assertStringContainsString(str_repeat('x', send_notification::REASON_MAX_LENGTH), $error);
        $this->assertStringNotContainsString(str_repeat('x', send_notification::REASON_MAX_LENGTH + 1), $error);
    }

    /**
     * The message is written in the recipient's language, and the task restores the current one.
     *
     * @return void
     */
    public function test_recipient_language(): void {
        global $CFG;
        /** @var \stdClass $cfg */
        $cfg = $CFG;
        $langfolder = self::str($cfg->dataroot) . '/lang/xx';
        check_dir_exists($langfolder);
        file_put_contents($langfolder . '/langconfig.php', "<?php\n\$string['thislanguage'] = 'XX';\n");
        file_put_contents(
            $langfolder . '/paygw_ifthenpay.php',
            "<?php\n\$string['notification:completed_subject'] = 'XX paid: {\$a->item}';\n"
        );
        get_string_manager()->reset_caches(true);

        [, , $tx] = $this->course_payer_tx();
        $manager = $this->getDataGenerator()->create_user(['lang' => 'xx']);
        $managerroleid = self::id($this->manager_role());
        role_assign($managerroleid, self::id($manager), \context_system::instance());

        $before = current_language();
        $sink = $this->redirectMessages();
        $this->run_task(['txid' => $tx->id, 'event' => 'completed', 'recipientid' => $manager->id, 'time' => time()]);
        $msg = $this->single_message($sink);

        $this->assertSame('XX paid: Advanced Widgets', $msg->subject);
        $this->assertSame($before, current_language());
    }

    /**
     * A recipient whose language is Portuguese gets the plugin's own Portuguese strings.
     *
     * PHPUnit installs only 'en', so a bare pt langconfig in dataroot marks pt as installed; the
     * plugin's lang/pt file then supplies the notification text.
     *
     * @return void
     */
    public function test_recipient_language_pt(): void {
        global $CFG;
        /** @var \stdClass $cfg */
        $cfg = $CFG;
        $langfolder = self::str($cfg->dataroot) . '/lang/pt';
        check_dir_exists($langfolder);
        file_put_contents($langfolder . '/langconfig.php', "<?php\n\$string['thislanguage'] = 'Português';\n");
        get_string_manager()->reset_caches(true);

        [, , $tx] = $this->course_payer_tx();
        $manager = $this->getDataGenerator()->create_user(['lang' => 'pt']);
        role_assign(self::id($this->manager_role()), self::id($manager), \context_system::instance());

        $sink = $this->redirectMessages();
        $this->run_task(['txid' => $tx->id, 'event' => 'completed', 'recipientid' => $manager->id, 'time' => time()]);
        $msg = $this->single_message($sink);

        $this->assertSame('Pagamento recebido: Advanced Widgets', $msg->subject);
        $this->assertStringContainsString('a encomenda foi entregue', self::str($msg->fullmessage));
    }

    /**
     * The manager role record.
     *
     * @return \stdClass|false
     */
    private function manager_role(): \stdClass|false {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        $role = $db->get_record('role', ['shortname' => 'manager']);
        return $role instanceof \stdClass ? $role : false;
    }

    /**
     * A recipient who lost the capability since queueing is skipped silently: nothing sent, no event.
     *
     * @return void
     */
    public function test_recipient_without_capability_skipped(): void {
        [, , $tx] = $this->course_payer_tx();
        $user = $this->getDataGenerator()->create_user();

        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();
        $this->run_task(['txid' => $tx->id, 'event' => 'completed', 'recipientid' => $user->id, 'time' => time()]);

        $this->assertSame(0, $messages->count());
        $this->assertSame([], array_filter($events->get_events(), fn($e) => $e instanceof notification_failed));
    }

    /**
     * A transaction or recipient that no longer exists ends the task quietly.
     *
     * @return void
     */
    public function test_missing_tx_or_recipient(): void {
        [, , $tx] = $this->course_payer_tx();
        $messages = $this->redirectMessages();
        $events = $this->redirectEvents();

        $this->run_task(['txid' => self::int($tx->id) + 1000, 'event' => 'completed', 'recipientid' => self::id(get_admin())]);
        $this->run_task(['txid' => $tx->id, 'event' => 'completed', 'recipientid' => 999999]);

        $this->assertSame(0, $messages->count());
        $this->assertSame([], array_filter($events->get_events(), fn($e) => $e instanceof notification_failed));
    }

    /**
     * When message_send() returns false, the failure is logged against the recipient and the task
     * does not throw (so Moodle does not retry it).
     *
     * message_send() returns false when a channel such as email fails after the message was stored;
     * that cannot be produced inside PHPUnit, so the send() seam is overridden to report it.
     *
     * @return void
     */
    public function test_send_false_logs_event(): void {
        [, , $tx] = $this->course_payer_tx();
        $adminid = self::id(get_admin());
        $failing = new class extends send_notification {
            /**
             * Report the message as not sent.
             *
             * @param \core\message\message $message Message.
             * @return int|false
             */
            protected function send(\core\message\message $message): int|false {
                return false;
            }
        };

        $events = $this->redirectEvents();
        $this->run_task(['txid' => $tx->id, 'event' => 'problem', 'problemtype' => 'mismatch', 'recipientid' => $adminid,
            'time' => time()], $failing);

        $failed = array_values(array_filter($events->get_events(), fn($e) => $e instanceof notification_failed));
        $this->assertCount(1, $failed);
        $this->assertSame($adminid, (int) $failed[0]->relateduserid);
        $this->assertSame(
            ['txid' => self::int($tx->id), 'event' => 'problem', 'problemtype' => 'mismatch', 'reason' => 'message_send() returned false'],
            $failed[0]->other
        );
        $this->assertStringContainsString('could not be sent', $failed[0]->get_description());
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
}
