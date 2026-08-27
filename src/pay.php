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

$cfg = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'ifthenpay');
if (!isset($cfg) || !is_array($cfg) || !array_key_exists('ifthenpay_state', $cfg) || empty($cfg['ifthenpay_state'])) {
    throw new moodle_exception('process:missing_ifthenpay_state', 'paygw_ifthenpay');
}

$token = random_string(8);

$state = \paygw_ifthenpay_decode_state($cfg);
if (empty($state->gatewaykey) || empty($state->methods)) {
    throw new moodle_exception('process:missing_ifthenpay_state', 'paygw_ifthenpay');
}
$payload = \paygw_ifthenpay\local\data_formatter::build_pay_by_link_payload($cost, $state, $token, $description);

$client = paygw_ifthenpay_api();
$order  = $client->create_pay_by_link($state->gatewaykey, $payload);

$redirect = (string) $order->redirect_url;
if ($redirect === '') {
    throw new moodle_exception('process:error_missing_redirect', 'paygw_ifthenpay');
}

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

redirect(new moodle_url($redirect));
