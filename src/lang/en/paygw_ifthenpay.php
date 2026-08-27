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
 * Strings for component 'paygw_ifthenpay', language 'en'
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Default.
$string['pluginname'] = 'ifthenpay';
// Shown in the payment chooser, beside this plugin's pix/img.svg. The logo already carries the
// brand and the modal is already titled "Select payment type", so neither is repeated here — the
// description says only what the customer can actually pay with.
$string['gatewayname'] = 'ifthenpay';
$string['gatewaydescription'] =
    '<span class="d-block mb-1">Pay securely by card, Apple Pay, Google Pay, MB WAY, Multibanco, Payshop or Pix.</span>' .
    '<span class="d-block small text-muted">Available methods depend on what your provider has activated.</span>';


// Modal (moustache).
$string['modal:redirectingtoifthenpay'] = 'Taking you to ifthenpay to complete your payment…';
// Settings / headings.
$string['api_heading'] = 'Connect your ifthenpay account';
$string['behavior_heading'] = 'Payment behaviour';
$string['behavior_desc'] = 'Optional settings affecting how this gateway is shown to users.';
// Onboarding and tips. Structure (list, numbering, badges) lives in the steps template — these
// carry prose only, so a translator never has to preserve Bootstrap classes.
$string['onboarding_step1'] =
    '<a href="https://ifthenpay.com/aderir/" target="_blank" rel="noopener">Subscribe and sign the contract</a>, ' .
    'selecting the payment methods you want to accept.';
$string['onboarding_step2'] = 'Once contracted, you will automatically receive a Backoffice Key — enter it below.';
$string['onboarding_step3'] =
    'Ask <a href="mailto:suporte@ifthenpay.com">ifthenpay support</a> for a Gateway Key <strong>for Moodle</strong>, ' .
    'with your chosen payment methods activated on it.';
$string['onboarding_step4'] = 'Everything else is configured here in Moodle.';
$string['onboarding_more_info'] =
    'Already contracted? Just request the Gateway Key. More information at ' .
    '<a href="https://ifthenpay.com" target="_blank" rel="noopener">ifthenpay.com</a>.';
$string['moodle_payment_tips_title'] = 'New to Moodle payments?';
$string['moodle_tip1'] =
    'Create a Payment Account: <em>Site administration → Payments → Payment accounts → Create payment account</em>.';
$string['moodle_tip2'] = 'Enable the ifthenpay gateway on that account (below).';
$string['moodle_tip3'] =
    'Enable <em>Enrolment on payment</em>: <em>Site administration → Plugins → Enrolments → Manage enrol plugins</em>.';
$string['moodle_tip4'] = 'Add it to a course: <em>Course → Participants → Enrolment methods → Add method</em>.';
$string['moodle_tips_links'] =
    '<a href="https://docs.moodle.org/501/en/Payment_gateways" target="_blank" rel="noopener">Payment gateways</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Set_up_payment" target="_blank" rel="noopener">Set up payment</a> · ' .
    '<a href="https://docs.moodle.org/400/en/Enrolment_on_payment" target="_blank" rel="noopener">Enrolment on payment</a>';
$string['backoffice_key'] = 'Backoffice Key';
$string['backoffice_key_desc'] = 'Used to authenticate API calls and webhooks.';
$string['methods_showcase_title'] = 'Supported payment methods';
$string['status_unconfigured_title'] = 'Not connected yet';
$string['status_unconfigured_desc'] =
    'ifthenpay is a free service. Four steps to start accepting payments.';
$string['status_connected_title'] = 'Connected to ifthenpay';
$string['status_connected_desc'] =
    'Your Backoffice Key is configured and ifthenpay is ready to accept payments. Need another payment method activated? ' .
    '<a href="mailto:suporte@ifthenpay.com">Contact ifthenpay support</a>.';
$string['onboarding_toggle'] = 'Show subscription steps';
$string['status_nomoodlekeys_title'] = 'No Gateway Key for Moodle yet';
$string['status_nomoodlekeys_desc'] =
    'Your Backoffice Key is valid, but no Gateway Key with Moodle context is assigned to it yet, so there is nothing to ' .
    'select on the payment account form. <a href="mailto:suporte@ifthenpay.com">Ask ifthenpay support</a> for one, with ' .
    'your chosen payment methods activated on it.';
$string['status_rejected_title'] = 'Backoffice Key rejected';
$string['status_rejected_desc'] =
    'ifthenpay did not recognise the Backoffice Key configured below, so payments cannot be processed. ' .
    'Check it against your ifthenpay backoffice, or <a href="mailto:suporte@ifthenpay.com">contact ifthenpay support</a>.';

// Validation / messages.
$string['error_invalidformat'] = 'Invalid format. Use 1234-5678-9012-3456.';
$string['error_invalid_backoffice_key'] = 'The Backoffice Key is not valid. Please check and try again.';
$string['error_missing_backoffice_key'] = 'The Backoffice Key is not configured. Please set it in the gateway settings.';


// Errors for API responses.
$string['api:nobackofficekey_error'] = 'API: No Backoffice Key configured.';
$string['api:error_invalid_pbl_response'] = 'Invalid response from Pay-by-Link API.';
$string['api:error_invalid_json_get'] = 'Invalid JSON on GET request: {$a}';
$string['api:error_invalid_json_post'] = 'Invalid JSON on POST request.';
$string['api:error_http_request_failed'] = 'HTTP request failed: {$a}';
$string['api:error_http_status'] = 'API HTTP error: {$a}';
$string['api:error_unauthorized'] = 'API rejected the credentials: {$a}';


// Form – sections & labels.
$string['form:gateway_key'] = 'Gateway Key';
$string['form:gateway_key_help'] = 'Need another key? <a href="mailto:suporte@ifthenpay.com">Contact ifthenpay support</a>. New keys and accounts appear automatically after activation.';

$string['form:payment_configuration'] = 'Payment methods';
$string['form:payment_configuration_reqnote'] = '<strong>Required:</strong> Please enable at least one payment method.';
$string['form:method_not_activated'] = 'Not activated for this Gateway Key &mdash; <a href="mailto:suporte@ifthenpay.com">ask ifthenpay support</a> to add it.';
$string['form:gateway_key_no_methods'] = 'This Gateway Key has no payment methods that this plugin supports, so none can be enabled below. Choose another Gateway Key, or <a href="mailto:suporte@ifthenpay.com">ask ifthenpay support</a> to add methods to it.';
$string['form:col_method'] = 'Method';
$string['form:col_account'] = 'Account';
$string['form:col_default'] = 'Default';

$string['form:default_method'] = 'Default method (Optional)';
$string['form:enable_method'] = 'Enable {$a}';
$string['form:set_default_method'] = 'Set {$a} as the default method';
$string['form:default_unsupported'] = 'This payment method cannot be preselected at checkout.';
$string['form:default_method_help'] =
    'Optional. If set, this method will be preselected at checkout when multiple methods are enabled. Leave as "None" to let the customer choose without a preset.';
$string['form:default_method_none'] = 'None';
$string['form:description'] = 'Checkout description (Optional)';
$string['form:description_help'] = 'Optional text, up to 150 characters, shown at checkout.';

$string['form:missing_backoffice_key_inline'] = 'Backoffice Key is not configured. <a href="{$a}">Open settings</a>.';
$string['form:rejected_backoffice_key_inline'] = 'ifthenpay did not recognise the configured Backoffice Key. <a href="{$a}">Open settings</a> to correct it.';
$string['form:missing_gateway_keys_inline'] =
    'No Gateway Key is configured for Moodle in your ifthenpay backoffice. Please <a href="mailto:suporte@ifthenpay.com">contact ifthenpay support</a> to create a Gateway Key for Moodle and assign the payment methods you intend to accept. After it’s created, return here and select it.';

// Validation / messages.
$string['form:error_unavailable_enable'] = 'This gateway cannot be enabled until a Gateway Key for Moodle is available. Clear this checkbox to save, which switches the gateway off and keeps the rest of its configuration.';
$string['form:error_state_missing'] = 'Configuration data is missing. Please try saving again.';
$string['form:error_no_methods_enabled'] = 'Please enable at least one payment method.';
$string['form:error_default_not_enabled'] = 'The default method "{$a}" must be enabled in Payment methods.';
$string['form:error_default_unknown'] = 'Selected default method "{$a}" is not recognised.';
$string['form:error_maxchars'] = 'Maximum {$a} characters.';
$string['form:error_callback_activation'] = 'Failed to activate payment notifications. Please check your Backoffice Key and internet connectivity, then save again. Error: {$a}';


// Proccessing => pay page.
$string['process:missing_ifthenpay_state'] = 'No configuration data found for ifthenpay. Please contact the site administrator.';
$string['process:error_missing_redirect']  = 'Missing redirect URL from ifthenpay. Please contact the site administrator.';

// Proccessing => cancel/error page.
$string['process:cancel_title']            = 'Payment not completed';
$string['process:cancel_desc_cancel']      = 'You canceled the payment before it was completed. Nothing has been charged.';
$string['process:cancel_desc_error']       = 'Something went wrong while confirming your payment. Nothing has been charged.';
$string['process:btn_try_again']           = 'Try again';
$string['process:btn_contact_support']     = 'Contact support';
$string['process:not_found']               = 'Payment attempt not found.';

// Processing => return page.
$string['process:return_title']            = 'Confirming your payment';
$string['process:waiting_hint']            = 'This usually takes a few seconds.';
$string['process:loading']                 = 'Checking';
$string['process:waiting_timeout']         = 'Still processing. You can close this page safely — your enrolment completes automatically as soon as ifthenpay confirms the payment.';
$string['process:order_reference']         = 'Order reference';
$string['process:transaction_id']          = 'Transaction ID';
$string['process:amount']                  = 'Amount';
$string['process:btn_retry']               = 'Check again';
$string['process:btn_go_to_courses']       = 'Go to My courses';


// Events.
$string['event:payment_problem'] = 'ifthenpay payment problem';


// Privacy strings.
$string['privacy:metadata:ifthenpay_tx'] = 'Minimal transaction tracking for the ifthenpay gateway.';
$string['privacy:metadata:ifthenpay_tx:userid'] = 'User ID associated with the transaction attempt.';
$string['privacy:metadata:ifthenpay_tx:timecreated'] = 'When the transaction was created.';
$string['privacy:metadata:ifthenpay_tx:timemodified'] = 'When the transaction was last updated.';
$string['privacy:metadata:ifthenpay_tx:token'] = 'Random token identifying the transaction attempt.';
$string['privacy:metadata:ifthenpay_tx:component'] = 'Component which initiated the payment.';
$string['privacy:metadata:ifthenpay_tx:paymentarea'] = 'Payment area within the component.';
$string['privacy:metadata:ifthenpay_tx:itemid'] = 'Item identifier inside the payment area.';
$string['privacy:metadata:ifthenpay_tx:accountid'] = 'Mapped ifthenpay account used for this payment.';
$string['privacy:metadata:ifthenpay_tx:amount'] = 'Payment amount.';
$string['privacy:metadata:ifthenpay_tx:currency'] = 'Payment currency.';
$string['privacy:metadata:ifthenpay_tx:redirect_url'] = 'Return URL used during the payment flow.';
$string['privacy:metadata:ifthenpay_tx:transaction_id'] = 'Transaction identifier returned by ifthenpay (if available).';
$string['privacy:metadata:ifthenpay_tx:paymentid'] = 'Link to the core payment record.';
$string['privacy:metadata:ifthenpay_tx:state'] = 'Current transaction state.';
