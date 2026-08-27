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
 * Return page for ifthenpay payments. Handles both UI and AJAX verification.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$token  = (string) required_param('token', PARAM_ALPHANUMEXT);
// Written into transaction_id char(64); constrain it here rather than letting the database
// reject it, which on the AJAX path would return HTML where the browser expects JSON.
$txid   = substr(required_param('txid', PARAM_ALPHANUMEXT), 0, 64);
$sk     = optional_param('sk', '', PARAM_RAW_TRIMMED);
$action = optional_param('action', '', PARAM_ALPHA);   // For AJAX polling.

global $USER, $PAGE, $OUTPUT;

// Fetch the record and confirm it belongs to the person asking: tokens travel in URLs, browser
// history and support tickets, so possession of one is not proof of ownership.
$rec = paygw_ifthenpay_tx_get($token);
if ($rec && (int) $rec->userid !== (int) $USER->id) {
    $rec = null;
}
if (!$rec) {
    if ($action === 'verify') {
        @header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['paid' => false, 'error' => 'notfound']);
        exit;
    }
    redirect(new moodle_url('/my/courses.php'));
}

// Record the transaction id once, as a single field: rewriting the whole row from this snapshot
// could undo a state the webhook set between the read above and this write.
if (empty($rec->transaction_id)) {
    paygw_ifthenpay_tx_set_transaction_id((int) $rec->id, $txid);
}

/*
 * AJAX verify endpoint, polled by amd/src/return.js while the customer waits.
 *
 * It only reports what the database already says. It must never mark a payment as paid, because
 * the only evidence available here is a txid from the browser's own URL, and ifthenpay's status
 * endpoint answers "was that transaction paid" — not "was it paid for this order". Trusting it
 * would let anyone settle their own pending order with a txid taken from any paid payment.
 *
 * webhook.php is the sole writer: there the amount and anti-phishing key arrive from ifthenpay
 * and are checked against the stored record, so they can actually fail.
 */
if ($action === 'verify') {
    @header('Content-Type: application/json; charset=utf-8');
    $fresh = paygw_ifthenpay_tx_get($token, 'id,state');
    echo json_encode(['paid' => $fresh && (string) $fresh->state === 'PAID']);
    exit;
}

/*
 * Only the rendered page needs this, and building it hits the database. Deriving it after the
 * verify branch keeps that work off every poll, and stops a failure here turning a JSON
 * endpoint into an HTML error page.
 */
$successurl = helper::get_success_url($rec->component, $rec->paymentarea, $rec->itemid);

// Already paid? Go straight to success.
if ((string)$rec->state === 'PAID') {
    redirect($successurl);
}

// Normal page (UI).
$params = ['token' => $token, 'txid' => $txid];
if ($sk !== '') {
    $params['sk'] = $sk;
}

$PAGE->set_url(new moodle_url('/payment/gateway/ifthenpay/return.php', $params));
$PAGE->set_context(context_system::instance());

// Strings.
$str = (object)[
    'title'   => get_string('process:return_title', 'paygw_ifthenpay'),
    'hint'    => get_string('process:waiting_hint', 'paygw_ifthenpay'),
    'timeout' => get_string('process:waiting_timeout', 'paygw_ifthenpay'),
    'loading' => get_string('process:loading', 'paygw_ifthenpay'),
    'ref'     => get_string('process:order_reference', 'paygw_ifthenpay'),
    'txid'    => get_string('process:transaction_id', 'paygw_ifthenpay'),
    'amount'  => get_string('process:amount', 'paygw_ifthenpay'),
    'retry'   => get_string('process:btn_retry', 'paygw_ifthenpay'),
    'courses' => get_string('process:btn_go_to_courses', 'paygw_ifthenpay'),
];

$PAGE->set_title($str->title);
$PAGE->set_heading(get_string('gatewayname', 'paygw_ifthenpay'));

// JS dataset + AMD boot. The same $selectors drive the template's ids, so the markup and the
// module cannot drift apart.
$coursesurl = (new moodle_url('/my/courses.php'))->out(false);
$selectors = (object) ['spinner' => 'ifp-spinner', 'status' => 'ifp-status', 'retry' => 'ifp-retry'];

$PAGE->requires->data_for_js('ifthenpay', (object) [
    'verifyUrl'  => (new moodle_url('/payment/gateway/ifthenpay/return.php', $params + ['action' => 'verify']))->out(false),
    'successUrl' => $successurl->out(false),
    'coursesUrl' => $coursesurl,
]);
$PAGE->requires->js_call_amd('paygw_ifthenpay/return', 'init', [$selectors, (object) ['timeout' => $str->timeout]]);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('paygw_ifthenpay/status_page', [
    'title' => $str->title,
    // The explanation doubles as the live region: the AMD module rewrites it if the wait runs long,
    // rather than the card carrying a second status line that just repeated the heading.
    'intro' => $str->hint,
    'spinner' => true,
    'spinnerid' => $selectors->spinner,
    'statusid' => $selectors->status,
    'loadinglabel' => $str->loading,
    'rows' => [
        ['label' => $str->ref, 'value' => $rec->token],
        ['label' => $str->txid, 'value' => $txid],
        ['label' => $str->amount, 'value' => $rec->amount . ' ' . $rec->currency, 'strong' => true],
    ],
    'retry' => ['id' => $selectors->retry, 'text' => $str->retry],
    'actions' => [['url' => $coursesurl, 'text' => $str->courses, 'primary' => false]],
]);

echo $OUTPUT->footer();
