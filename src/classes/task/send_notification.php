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

use paygw_ifthenpay\local\notification_recipients;
use stdClass;

/**
 * Sends one staff payment notification to one recipient.
 *
 * Queued by paygw_ifthenpay_notify() from the webhook, so that no message is built or sent while
 * ifthenpay waits for its answer. Custom data: txid, event ('completed' or 'problem'),
 * problemtype ('badkey', 'mismatch', 'delivery' or 'error', problems only), recipientid, time
 * (when the event happened) and reason (technical detail, 'error' problems only).
 *
 * message_send() is called once. Its `false` means Moodle refused the message or a channel failed
 * after the notification was stored; neither is worth retrying (a retry would duplicate the popup),
 * so it is logged as a notification_failed event and the task ends normally. An exception can only
 * come from before the message was stored, so it is left to propagate and Moodle retries the task.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notification extends \core\task\adhoc_task {
    /** @var int Longest technical reason shown in an 'error' problem notification. */
    public const REASON_MAX_LENGTH = 200;

    /** @var string[] Problem types, each with a notification:problem_<type> string. */
    public const PROBLEM_TYPES = ['badkey', 'mismatch', 'delivery', 'error'];

    /**
     * Task name, for the admin task screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task:send_notification', 'paygw_ifthenpay');
    }

    /**
     * Build and send the notification.
     *
     * @return void
     */
    public function execute(): void {
        global $CFG, $DB;

        $data = $this->get_custom_data();
        if (!$data instanceof stdClass) {
            return;
        }
        $txid = is_numeric($data->txid ?? null) ? (int) $data->txid : 0;
        $recipientid = is_numeric($data->recipientid ?? null) ? (int) $data->recipientid : 0;
        $event = ($data->event ?? null) === 'problem' ? 'problem' : 'completed';
        $problemtype = null;
        if ($event === 'problem') {
            $problemtype = in_array($data->problemtype ?? null, self::PROBLEM_TYPES, true) ? (string) $data->problemtype : 'error';
        }
        $time = is_numeric($data->time ?? null) ? (int) $data->time : time();
        $reason = is_string($data->reason ?? null) ? $data->reason : '';
        $provider = $event === 'problem' ? notification_recipients::PROVIDER_PROBLEM : notification_recipients::PROVIDER_COMPLETED;

        /** @var \moodle_database $db */
        $db = $DB;
        $tx = $db->get_record('paygw_ifthenpay_tx', ['id' => $txid]);
        $recipient = \core_user::get_user($recipientid);
        if (!$tx instanceof stdClass || !$recipient instanceof stdClass) {
            return;
        }
        if (!empty($recipient->deleted) || !empty($recipient->suspended) || ($recipient->auth ?? '') === 'nologin') {
            return;
        }
        // Lost the capability since queueing: Moodle would refuse the message; skipped silently (R8a).
        if (!notification_recipients::can_receive($recipientid, $provider)) {
            return;
        }

        /** @var \stdClass $cfg */
        $cfg = $CFG;
        $lang = self::str($recipient->lang ?? null) ?: self::str($cfg->lang ?? null);
        $previouslang = force_current_language($lang);
        try {
            $message = $this->build_message($tx, $recipient, $provider, $problemtype, $time, $reason);
        } finally {
            force_current_language($previouslang);
        }

        if ($this->send($message) === false) {
            \paygw_ifthenpay\event\notification_failed::create([
                'context' => \context_system::instance(),
                'relateduserid' => $recipientid,
                'other' => [
                    'txid' => $txid,
                    'event' => $event,
                    'problemtype' => $problemtype,
                    'reason' => 'message_send() returned false',
                ],
            ])->trigger();
        }
    }

    /**
     * Hand the message to Moodle's messaging, once.
     *
     * A seam for tests: message_send() returns false both for refusals before the message is stored
     * and for a channel (e.g. email) failing after it was stored, and neither can be produced
     * reliably inside PHPUnit, where messages and emails go to sinks.
     *
     * @param \core\message\message $message Message to send.
     * @return int|false Message id, or false when Moodle reports it was not sent.
     */
    protected function send(\core\message\message $message): int|false {
        $result = message_send($message);
        return is_numeric($result) ? (int) $result : false;
    }

    /**
     * Build the message, in the current (forced) language and the recipient's timezone.
     *
     * @param stdClass $tx Transaction record.
     * @param stdClass $recipient Recipient user record.
     * @param string $provider One of the notification_recipients::PROVIDER_* constants.
     * @param string|null $problemtype Problem type, for problem notifications.
     * @param int $time When the event happened.
     * @param string $reason Technical detail, shown (truncated) for 'error' problems only.
     * @return \core\message\message
     */
    protected function build_message(
        stdClass $tx,
        stdClass $recipient,
        string $provider,
        ?string $problemtype,
        int $time,
        string $reason
    ): \core\message\message {
        $component = 'paygw_ifthenpay';

        // Payer.
        $payerid = is_numeric($tx->userid ?? null) ? (int) $tx->userid : 0;
        $payer = \core_user::get_user($payerid);
        $payername = $payer instanceof stdClass ? fullname($payer) : get_string('notification:unknownuser', $component);
        $payerurl = new \moodle_url('/user/profile.php', ['id' => $payerid]);

        // Course or item.
        $courseid = notification_recipients::courseid($tx);
        $course = $courseid ? get_course($courseid) : null;
        if ($courseid && $course) {
            $coursecontext = \context_course::instance($courseid);
            // Raw text (filters applied, not HTML-escaped): every value here is plain text, escaped
            // exactly once with s() where it goes into the HTML body.
            $itemname = format_string(self::str($course->fullname), true, ['context' => $coursecontext, 'escape' => false]);
            $itemurl = new \moodle_url('/course/view.php', ['id' => $courseid]);
        } else {
            $itemname = get_string('notification:item_other', $component, (object) [
                'component' => self::str($tx->component ?? null),
                'paymentarea' => self::str($tx->paymentarea ?? null),
                'itemid' => self::str($tx->itemid ?? null),
            ]);
            $itemurl = null;
        }

        $amount = \core_payment\helper::get_cost_as_string(
            is_numeric($tx->amount ?? null) ? (float) $tx->amount : 0.0,
            self::str($tx->currency ?? null)
        );
        $reference = self::str($tx->token ?? null);
        $date = userdate($time, get_string('strftimedatetime', 'langconfig'), \core_date::get_user_timezone($recipient));

        $a = (object) ['item' => $itemname, 'payer' => $payername];
        if ($problemtype === null) {
            $subject = get_string('notification:completed_subject', $component, $a);
            $intro = get_string('notification:completed_intro', $component, $a);
        } else {
            $subject = get_string('notification:problem_subject', $component, $a);
            $intro = get_string('notification:problem_intro', $component, $a);
        }

        // Label => [plain value, html value].
        $rows = [];
        $rows[get_string('notification:payer', $component)] = [
            $payername . ' (' . $payerurl->out(false) . ')',
            \html_writer::link($payerurl, s($payername)),
        ];
        $rows[get_string('notification:item', $component)] = $itemurl
            ? [$itemname . ' (' . $itemurl->out(false) . ')', \html_writer::link($itemurl, s($itemname))]
            : [$itemname, s($itemname)];
        $rows[get_string('notification:amount', $component)] = [$amount, s($amount)];
        $rows[get_string('notification:reference', $component)] = [$reference, s($reference)];
        $rows[get_string('notification:date', $component)] = [$date, s($date)];
        if ($problemtype !== null) {
            $problem = get_string('notification:problem_' . $problemtype, $component);
            if ($problemtype === 'error' && $reason !== '') {
                $detail = \core_text::substr((string) preg_replace('/[[:cntrl:]]/', ' ', $reason), 0, self::REASON_MAX_LENGTH);
                $problem .= ' ' . get_string('notification:problem_detail', $component, $detail);
            }
            $rows[get_string('notification:problem', $component)] = [$problem, s($problem)];
        }

        $plain = $intro . "\n\n";
        $html = \html_writer::tag('p', s($intro)) . '<ul>';
        foreach ($rows as $label => [$plainvalue, $htmlvalue]) {
            $plain .= $label . ': ' . $plainvalue . "\n";
            $html .= \html_writer::tag('li', \html_writer::tag('strong', s($label)) . ': ' . $htmlvalue);
        }
        $html .= '</ul>';

        $message = new \core\message\message();
        $message->component = $component;
        $message->name = $provider;
        $message->userfrom = \core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->notification = 1;
        if ($courseid && $course) {
            $message->courseid = $courseid;
        }
        $message->subject = $subject;
        $message->fullmessage = $plain;
        $message->fullmessageformat = (int) FORMAT_PLAIN;
        $message->fullmessagehtml = $html;
        $message->smallmessage = $subject;
        $message->contexturl = $itemurl ? $itemurl->out(false) : $payerurl->out(false);
        $message->contexturlname = $itemurl ? $itemname : $payername;
        return $message;
    }

    /**
     * A scalar value as a string; anything else as ''.
     *
     * @param mixed $value Value read from a record.
     * @return string
     */
    private static function str(mixed $value): string {
        return is_scalar($value) ? (string) $value : '';
    }
}
