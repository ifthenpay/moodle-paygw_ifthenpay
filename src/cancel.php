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

$token = (string) required_param('token', PARAM_ALPHANUMEXT);
$type  = required_param('type', PARAM_ALPHA);        // CANCEL or ERROR.

global $USER, $CFG, $PAGE, $OUTPUT;

// Fetch the transaction, and treat someone else's as absent: a token in a URL is not a credential.
$rec = paygw_ifthenpay_tx_get($token);
if ($rec && (int) $rec->userid !== (int) $USER->id) {
    $rec = null;
}

$str = (object)[
    'title'       => get_string('process:cancel_title', 'paygw_ifthenpay'),
    'desccancel'  => get_string('process:cancel_desc_cancel', 'paygw_ifthenpay'),
    'descerror'   => get_string('process:cancel_desc_error', 'paygw_ifthenpay'),
    'ref'         => get_string('process:order_reference', 'paygw_ifthenpay'),
    'amount'      => get_string('process:amount', 'paygw_ifthenpay'),
    'tryagain'    => get_string('process:btn_try_again', 'paygw_ifthenpay'),
    'support'     => get_string('process:btn_contact_support', 'paygw_ifthenpay'),
    'notfound'    => get_string('process:not_found', 'paygw_ifthenpay'),
];

$params = ['token' => $token, 'type' => $type];
$PAGE->set_url(new moodle_url('/payment/gateway/ifthenpay/cancel.php', $params));
$PAGE->set_context(context_system::instance());
$PAGE->set_title($str->title);
$PAGE->set_heading(get_string('gatewayname', 'paygw_ifthenpay'));

echo $OUTPUT->header();

if (!$rec) {
    echo $OUTPUT->notification($str->notfound, 'notifyproblem');
    echo html_writer::div(
        html_writer::link(new moodle_url('/'), get_string('back'), ['class' => 'btn btn-secondary']),
        'mt-3'
    );
    echo $OUTPUT->footer();
    exit;
}

// Persist the outcome. The "not already paid" condition lives in the UPDATE, so a webhook that
// settles the payment while this page is open cannot be overwritten.
$newstate = (strtoupper($type) === 'ERROR') ? 'ERROR' : 'CANCELED';
if ((string) $rec->state !== 'PAID' && (string) $rec->state !== $newstate) {
    paygw_ifthenpay_tx_set_unpaid_state((int) $rec->id, $newstate);
}

/*
 * Re-read before rendering. A Multibanco payment can settle while this redirect is in flight — the
 * UPDATE above correctly refuses to overwrite PAID, but showing "Payment not completed" to someone
 * who has just been enrolled would be alarming and wrong.
 */
$fresh = paygw_ifthenpay_tx_get($token, 'id,state,component,paymentarea,itemid');
if ($fresh && (string) $fresh->state === 'PAID') {
    redirect(helper::get_success_url($fresh->component, $fresh->paymentarea, $fresh->itemid));
}

$iserror = ($newstate === 'ERROR');
$actions = [[
    'url' => helper::get_success_url($rec->component, $rec->paymentarea, $rec->itemid)->out(false),
    'text' => $str->tryagain,
    'primary' => true,
]];
if (!empty($CFG->supportemail)) {
    $actions[] = ['url' => 'mailto:' . $CFG->supportemail, 'text' => $str->support, 'primary' => false];
}

echo $OUTPUT->render_from_template('paygw_ifthenpay/status_page', [
    'title' => $str->title,
    'intro' => $iserror ? $str->descerror : $str->desccancel,
    'icon' => $OUTPUT->pix_icon(
        $iserror ? 'i/invalid' : 'i/warning',
        '',
        'core',
        ['class' => 'icon ' . ($iserror ? 'text-danger' : 'text-warning')]
    ),
    'rows' => [
        ['label' => $str->ref, 'value' => $rec->token],
        ['label' => $str->amount, 'value' => $rec->amount . ' ' . $rec->currency, 'strong' => true],
    ],
    'actions' => $actions,
]);

echo $OUTPUT->footer();
