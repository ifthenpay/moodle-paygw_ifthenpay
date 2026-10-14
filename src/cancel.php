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
 * Payment cancelled or failed page (redirect from ifthenpay).
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$token = required_param('token', PARAM_ALPHANUMEXT);
$type  = required_param('type', PARAM_ALPHA);        // CANCEL or ERROR.
$tab   = optional_param('tab', 0, PARAM_BOOL);       // The checkout ran in its own tab (see pay.php).

// Set before anything can redirect: redirect() and the renderer both expect a page context.
$PAGE->set_context(context_system::instance());

$iserror = strtoupper($type) === 'ERROR';
$rec = paygw_ifthenpay_tx_get_own($token);

// Settled before anything is output, so a paid order can still be redirected.
if ($rec) {
    // Persist the outcome. The "not already paid" condition lives in the UPDATE, so a webhook that
    // settles the payment while this page is open cannot be overwritten.
    $newstate = $iserror ? 'ERROR' : 'CANCELED';
    if ((string) $rec->state !== 'PAID' && (string) $rec->state !== $newstate) {
        paygw_ifthenpay_tx_set_unpaid_state((int) $rec->id, $newstate);
    }

    /*
     * Re-read before rendering. A Multibanco payment can settle while this redirect is in flight —
     * the UPDATE above correctly refuses to overwrite PAID, but showing "Payment not completed" to
     * someone who has just been enrolled would be alarming and wrong.
     */
    $fresh = paygw_ifthenpay_tx_get($token, 'id,state');
    if ($fresh && (string) $fresh->state === 'PAID') {
        // A checkout tab goes to the page that closes it; the Moodle page behind it continues on its own.
        redirect($tab
            ? new moodle_url('/payment/gateway/ifthenpay/return.php', ['token' => $token, 'tab' => 1])
            : helper::get_success_url($rec->component, $rec->paymentarea, $rec->itemid));
    }
}

$title = get_string('process:cancel_title', 'paygw_ifthenpay');
$params = ['token' => $token, 'type' => $type] + ($tab ? ['tab' => 1] : []);
$PAGE->set_url(new moodle_url('/payment/gateway/ifthenpay/cancel.php', $params));
$PAGE->set_title($title);
$PAGE->set_heading(get_string('gatewayname', 'paygw_ifthenpay'));

if ($tab) {
    // Closes the checkout tab; if the browser refuses, the page below is what the customer sees.
    $PAGE->requires->js_call_amd('paygw_ifthenpay/checkout_tab', 'close');
}

echo $OUTPUT->header();

if (!$rec) {
    echo $OUTPUT->notification(get_string('process:not_found', 'paygw_ifthenpay'), 'notifyproblem');
    echo html_writer::div(
        html_writer::link(new moodle_url('/'), get_string('back'), ['class' => 'btn btn-secondary']),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

$actions = [[
    'url' => helper::get_success_url($rec->component, $rec->paymentarea, $rec->itemid)->out(false),
    'text' => get_string('process:btn_try_again', 'paygw_ifthenpay'),
    'primary' => true,
]];
if (!empty($CFG->supportemail)) {
    $actions[] = [
        'url' => 'mailto:' . $CFG->supportemail,
        'text' => get_string('process:btn_contact_support', 'paygw_ifthenpay'),
        'primary' => false,
    ];
}

echo $OUTPUT->render_from_template('paygw_ifthenpay/status_page', [
    'title' => $title,
    'intro' => get_string($iserror ? 'process:cancel_desc_error' : 'process:cancel_desc_cancel', 'paygw_ifthenpay'),
    'icon' => $OUTPUT->pix_icon(
        $iserror ? 'i/invalid' : 'i/warning',
        '',
        'core',
        ['class' => 'icon ' . ($iserror ? 'text-danger' : 'text-warning')]
    ),
    'rows' => paygw_ifthenpay_status_rows($rec),
    'actions' => $actions,
]);

echo $OUTPUT->footer();
