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
 * Redirects to the ifthenpay checkout for payment
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_payment\helper;

// Asked for by the checkout opened in its own tab, which needs the URL rather than a redirect. Set
// before config.php so a failure comes back as JSON, not as an HTML error page the script cannot read.
// phpcs:ignore moodle.Files.MoodleInternal.MoodleInternalGlobalState
$asjson = (($_GET['format'] ?? '') === 'json');
if ($asjson) {
    define('AJAX_SCRIPT', true);
}

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

$component = required_param('component', PARAM_ALPHANUMEXT);
$paymentarea = required_param('paymentarea', PARAM_ALPHANUMEXT);
$itemid = required_param('itemid', PARAM_INT);
// PHP has already decoded the query string; decoding again would reinstate characters PARAM_TEXT
// just cleaned out.
$description = required_param('description', PARAM_TEXT);

// Amount, currency and account come from the component, never from the request.
$payable   = helper::get_payable($component, $paymentarea, $itemid);
$surcharge = helper::get_gateway_surcharge('ifthenpay');
$cost      = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);

$state = paygw_ifthenpay_decode_state(helper::get_gateway_configuration($component, $paymentarea, $itemid, 'ifthenpay'));
if (empty($state->gatewaykey) || empty($state->methods)) {
    throw new moodle_exception('process:missing_ifthenpay_state', 'paygw_ifthenpay');
}

$token = random_string(8);
$payload = \paygw_ifthenpay\local\data_formatter::build_pay_by_link_payload($cost, $state, $token, $description, $asjson);
$redirect = paygw_ifthenpay_api()->create_pay_by_link($state->gatewaykey, $payload);

// The webhook matches on this record to finalise the payment and deliver the order, so it is
// written before the customer is sent anywhere. Failing here is better than taking money for
// something that could never be delivered.
paygw_ifthenpay_tx_create([
    'token'        => $token,
    'userid'       => $USER->id,
    'component'    => $component,
    'paymentarea'  => $paymentarea,
    'itemid'       => $itemid,
    'accountid'    => $payable->get_account_id(),
    'amount'       => $cost,
    'currency'     => $payable->get_currency(),
    'gateway_key'  => $state->gatewaykey,
    'redirect_url' => $redirect,
]);

if ($asjson) {
    @header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['token' => $token, 'url' => $redirect]);
    exit;
}

redirect(new moodle_url($redirect));
