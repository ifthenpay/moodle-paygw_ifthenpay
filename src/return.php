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

// The polling request runs every few seconds and never writes to the session, so it asks not to hold
// the session lock: the lock would stall the customer's other Moodle requests until each one finished.
// Its answer is JSON, so debugging output goes to the error log rather than into it.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
if (($_GET['action'] ?? '') === 'verify') {
    define('READ_ONLY_SESSION', true);
    define('NO_DEBUG_DISPLAY', true);
}

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

$action = optional_param('action', '', PARAM_ALPHA);   // For AJAX polling.

/**
 * Send a JSON response to the polling script and stop.
 *
 * @param array<string, mixed> $data Response body.
 * @return void Never returns: the script exits.
 */
function paygw_ifthenpay_return_json(array $data): void {
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// The poll must always answer JSON. A plain require_login() would redirect an expired session to
// the login page, and the poller would receive HTML where it expects JSON.
if ($action === 'verify') {
    try {
        require_login(null, false, null, false, true);
    } catch (\moodle_exception $e) {
        paygw_ifthenpay_return_json(['paid' => false, 'state' => null, 'error' => 'login']);
    }
} else {
    require_login();
}

// Set before anything can redirect: redirect() and the renderer both expect a page context.
$PAGE->set_context(context_system::instance());

$token = required_param('token', PARAM_ALPHANUMEXT);
// Set on the URLs given to ifthenpay when the checkout runs in its own tab (see pay.php).
$tab   = optional_param('tab', 0, PARAM_BOOL);

$rec = paygw_ifthenpay_tx_get_own($token);
if (!$rec) {
    if ($action === 'verify') {
        paygw_ifthenpay_return_json(['paid' => false, 'state' => 'NOTFOUND', 'error' => 'notfound']);
    }
    redirect(new moodle_url('/my/courses.php'));
}

/*
 * AJAX verify endpoint, polled by amd/src/return.js while the customer waits.
 *
 * It only reports what the database already says. It must never mark a payment as paid, because
 * this URL is in the customer's hands: nothing it carries is evidence that the order was paid.
 *
 * webhook.php is the sole writer: there the amount and anti-phishing key arrive from ifthenpay
 * and are checked against the stored record, so they can actually fail.
 */
if ($action === 'verify') {
    // The state lets the waiting page tell a cancelled or failed attempt from one still pending.
    $current = (string) $rec->state;
    paygw_ifthenpay_return_json(['paid' => $current === 'PAID', 'state' => $current]);
}

/*
 * Only the rendered page needs this, and building it hits the database. Deriving it after the
 * verify branch keeps that work off every poll, and stops a failure here turning a JSON
 * endpoint into an HTML error page.
 */
$successurl = helper::get_success_url($rec->component, $rec->paymentarea, $rec->itemid);

// Already paid? Go straight to success. A checkout tab stays put instead: the script below closes it,
// and the Moodle page that opened it carries on to the success URL.
if ((string)$rec->state === 'PAID' && !$tab) {
    redirect($successurl);
}

// Normal page (UI).
$params = ['token' => $token] + ($tab ? ['tab' => 1] : []);
$title = get_string('process:return_title', 'paygw_ifthenpay');

$PAGE->set_url(new moodle_url('/payment/gateway/ifthenpay/return.php', $params));
$PAGE->set_title($title);
$PAGE->set_heading(get_string('gatewayname', 'paygw_ifthenpay'));

// JS dataset + AMD boot. The same $selectors drive the template's ids, so the markup and the
// module cannot drift apart.
$coursesurl = (new moodle_url('/my/courses.php'))->out(false);
$selectors = (object) ['spinner' => 'ifp-spinner', 'status' => 'ifp-status', 'retry' => 'ifp-retry'];

$PAGE->requires->data_for_js('ifthenpay', (object) [
    'verifyUrl'  => (new moodle_url('/payment/gateway/ifthenpay/return.php', $params + ['action' => 'verify']))->out(false),
    'successUrl' => $successurl->out(false),
    'coursesUrl' => $coursesurl,
    'tab'        => (bool) $tab,
]);
$PAGE->requires->js_call_amd('paygw_ifthenpay/return', 'init', [
    $selectors,
    (object) ['timeout' => get_string('process:waiting_timeout', 'paygw_ifthenpay')],
]);

echo $OUTPUT->header();

echo $OUTPUT->render_from_template('paygw_ifthenpay/status_page', [
    'title' => $title,
    // The explanation doubles as the live region: the AMD module rewrites it if the wait runs long,
    // rather than the card carrying a second status line that just repeated the heading.
    'intro' => get_string('process:waiting_hint', 'paygw_ifthenpay'),
    'spinner' => true,
    'spinnerid' => $selectors->spinner,
    'statusid' => $selectors->status,
    'loadinglabel' => get_string('process:loading', 'paygw_ifthenpay'),
    'rows' => paygw_ifthenpay_status_rows($rec),
    'retry' => ['id' => $selectors->retry, 'text' => get_string('process:btn_retry', 'paygw_ifthenpay')],
    'actions' => [
        ['url' => $coursesurl, 'text' => get_string('process:btn_go_to_courses', 'paygw_ifthenpay'), 'primary' => false],
    ],
]);

echo $OUTPUT->footer();
