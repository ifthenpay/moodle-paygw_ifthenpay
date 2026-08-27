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
 * Builds and validates the per-account ifthenpay configuration form.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

use core_payment\form\account_gateway;

/**
 * The form an admin fills in per payment account: which Gateway Key to use, which payment methods
 * to accept, and which of them to preselect at checkout.
 *
 * Options always come from the live ifthenpay dataset; the saved configuration only decides which
 * of them start selected. A hidden JSON field mirrors the visible form so the server validates
 * what the admin actually sees.
 */
class config_form
{
    /**
     * Length limit on the admin's checkout description.
     *
     * Deliberately below the Pay-by-Link cap of 200 (data_formatter::API_DESCRIPTION_MAX_LENGTH):
     * this text is appended to an order reference and the payable item's own description, so the
     * headroom is what keeps a normal course name from being truncated at payment time.
     */
    private const DESCRIPTION_MAX_LENGTH = 150;

    /**
     * Add the ifthenpay fields to the account gateway form.
     *
     * @param account_gateway $form The account gateway form wrapper.
     * @return void
     */
    public static function build(account_gateway $form): void {
        $mform = $form->get_mform();

        // The status separates a key ifthenpay refuses from one that simply has no Moodle
        // Gateway Key yet, which need different explanations.
        $status = \paygw_ifthenpay_backoffice_key_status();
        if ($status !== 'ok') {
            self::render_unavailable($mform, $status, self::get_saved_config($form));
            return;
        }

        // Methods arrive ordered by catalog position, so iterating them preserves that order.
        $dataset  = \paygw_ifthenpay_build_full_admin_dataset();
        $gkmap    = $dataset['gatewaykeys'];
        $methods  = $dataset['methods'];
        $accounts = $dataset['accounts'];
        $saved    = self::get_saved_config($form);

        // The saved Gateway Key while it still exists, otherwise the first available.
        $savedgk   = (string) ($saved['ifthenpay_gatewaykey'] ?? '');
        $currentgk = isset($gkmap[$savedgk]) ? $savedgk : (string) array_key_first($gkmap);

        self::render_gateway_key($mform, $gkmap, $currentgk);
        self::render_methods($mform, $methods, $accounts, $currentgk, $saved);
        self::render_description($mform, $saved);

        // Mirrors the UI on submission; initialised here from the saved defaults.
        $mform->addElement('hidden', 'ifthenpay_state', json_encode(
            self::build_initial_state($methods, $saved, $currentgk)
        ));
        $mform->setType('ifthenpay_state', PARAM_RAW);

        global $PAGE;

        // Via data_for_js, not js_call_amd: Moodle caps AMD call arguments at 1024 characters and
        // warns loudly past it, which a multi-key account map exceeds.
        $PAGE->requires->data_for_js('ifthenpay', (object) [
            'accounts' => $accounts,
            // The rows actually rendered, in order. The module must not infer this from the
            // accounts map: a method with no account on any Gateway Key still has a row, and
            // deriving the list would drop it from the state the server validates.
            'methods' => array_keys($methods),
            'notActivated' => get_string('form:method_not_activated', 'paygw_ifthenpay'),
            'preselectable' => \paygw_ifthenpay_preselectable_methods(),
        ]);

        $PAGE->requires->js_call_amd('paygw_ifthenpay/admin_gateway_form', 'init', [
            (object) [
                'gatewayKey'    => 'ifthenpay_gatewaykey',
                'accountPrefix' => 'ifthenpay_account_',
                'enablePrefix'  => 'ifthenpay_enable_',
                'state'         => 'ifthenpay_state',
                'defaultMethod' => 'ifthenpay_defaultmethod',
                'description'   => 'ifthenpay_description',
            ],
        ]);
    }

    /**
     * Explain why the gateway cannot be configured, and let the admin switch it off.
     *
     * "Enable" is deliberately left editable. Nothing else on the site can disable a gateway —
     * payment/accounts.php only shows the status as an icon — so this form is the only way out. If
     * a key stops working while the gateway is on, freezing the checkbox would strand the admin
     * with a gateway customers can still choose and never complete. Turning it off is the one
     * useful action left, so it stays available; validate() refuses a save that leaves it on.
     *
     * The saved configuration is re-registered as hidden fields because core rebuilds the stored
     * config from whatever the form submits: without them, switching the gateway off would also
     * erase the Gateway Key, methods and description that a later working key would want back.
     *
     * @param \MoodleQuickForm $mform  Form.
     * @param string           $status Key status from paygw_ifthenpay_backoffice_key_status().
     * @param array<string, mixed> $saved Saved gateway configuration, preserved across the save.
     * @return void
     */
    private static function render_unavailable(\MoodleQuickForm $mform, string $status, array $saved): void {
        global $OUTPUT;

        foreach ($saved as $name => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $mform->addElement('hidden', $name, (string) $value);
            $mform->setType($name, PARAM_RAW);
        }

        if ($status === 'nomoodlekeys') {
            $message = get_string('form:missing_gateway_keys_inline', 'paygw_ifthenpay');
        } else {
            // A rejected key is configured but refused, so it needs its own wording: telling an
            // admin it "is not configured" would send them looking for the wrong problem.
            $message = get_string(
                $status === 'rejected' ? 'form:rejected_backoffice_key_inline' : 'form:missing_backoffice_key_inline',
                'paygw_ifthenpay',
                (new \moodle_url('/admin/settings.php', ['section' => 'paymentgatewayifthenpay']))->out(false)
            );
        }

        $mform->addElement('static', 'ifthenpay_unavailable', '', $OUTPUT->notification($message, 'warning', false));
    }

    /**
     * Gateway Key selector. Kept prominent: a client can hold several, and it drives everything below.
     *
     * @param \MoodleQuickForm $mform     Form.
     * @param array<string, string> $gkmap  Map of gatewayKey => label.
     * @param string           $currentgk Current gateway key.
     * @return void
     */
    private static function render_gateway_key(\MoodleQuickForm $mform, array $gkmap, string $currentgk): void {
        $mform->addElement('select', 'ifthenpay_gatewaykey', get_string('form:gateway_key', 'paygw_ifthenpay'), $gkmap);
        $mform->setDefault('ifthenpay_gatewaykey', $currentgk);
        $mform->setType('ifthenpay_gatewaykey', PARAM_RAW_TRIMMED);
        $mform->addRule('ifthenpay_gatewaykey', null, 'required', null, 'server');
        $mform->addHelpButton('ifthenpay_gatewaykey', 'form:gateway_key', 'paygw_ifthenpay');
    }

    /**
     * Payment methods, one aligned row each: enable, method, bound account, default.
     *
     * Columns line up across rows via Bootstrap grid classes rather than a literal <table>: the
     * enable checkbox and default radio must be registered QuickForm elements to be persisted
     * (core's account_gateway form builds the stored config from the form's own fields), and
     * QuickForm renders each element with its own row markup, which a table cannot host. Grouping
     * the elements per row and sizing them with col-* gives the same aligned columns with no
     * plugin CSS and no shadow inputs to keep in sync.
     *
     * The account is shown read-only: a gateway key binds exactly one account per method, so there
     * is no choice to offer. Its value still submits, via a hidden field.
     *
     * @param \MoodleQuickForm $mform     Form.
     * @param array<string, MethodMeta>            $methods   Methods meta, in display order.
     * @param array<string, array<string, string>> $accounts  Accounts: GK => methodKey => account.
     * @param string                               $currentgk Current gateway key.
     * @param array<string, mixed>                 $saved     Saved config for defaults.
     * @return void
     */
    private static function render_methods(
        \MoodleQuickForm $mform,
        array $methods,
        array $accounts,
        string $currentgk,
        array $saved
    ): void {
        global $OUTPUT;

        $bymethod = $accounts[$currentgk] ?? [];
        $preselectable = \paygw_ifthenpay_preselectable_methods();

        /*
         * A Gateway Key can carry only methods this plugin does not offer (Cofidis, Bizum), which
         * leaves nothing selectable here. Without saying so, the admin would find every row greyed
         * out and hit "enable at least one payment method" with no explanation. Rendered always and
         * hidden when it does not apply, so the AMD module can toggle it as the Gateway Key changes.
         */
        $mform->addElement(
            'static',
            'ifthenpay_nomethods',
            '',
            \html_writer::div(
                $OUTPUT->notification(get_string('form:gateway_key_no_methods', 'paygw_ifthenpay'), 'warning', false),
                '',
                ['id' => 'ifthenpay-no-methods', 'class' => $bymethod === [] ? '' : 'd-none']
            )
        );

        $mform->addElement(
            'static',
            'ifthenpay_methods_note',
            get_string('form:payment_configuration', 'paygw_ifthenpay'),
            \html_writer::div(get_string('form:payment_configuration_reqnote', 'paygw_ifthenpay'), 'text-muted small')
        );

        // Column headings, so the account and default columns are not left unlabelled. Added as a
        // group rather than a static element on purpose: a group's class lands on the form-row
        // wrapper, which is where the method rows below compute their col-* widths from, whereas
        // static content is confined to the narrower value cell and would not line up.
        $headercols = '';
        foreach (
            [
                'col-1' => '',
                'col-4' => get_string('form:col_method', 'paygw_ifthenpay'),
                'col-5' => get_string('form:col_account', 'paygw_ifthenpay'),
                'col-2' => get_string('form:col_default', 'paygw_ifthenpay'),
            ] as $cols => $text
        ) {
            // Bootstrap 5 spells this fw-bold and Bootstrap 4 font-weight-bold; both are emitted
            // so the styling holds on Moodle 4.3-4.5 and on 5.x, where the BS4 name is deprecated.
            $headercols .= \html_writer::div(
                $text,
                $cols . ' px-0 text-uppercase text-muted small fw-bold font-weight-bold'
            );
        }
        $header = $mform->addGroup(
            [$mform->createElement('html', $headercols)],
            'ifthenpay_methods_header',
            '',
            '',
            false
        );
        $header->setAttributes(['class' => 'row align-items-center w-100 m-0 border-bottom pb-1']);

        foreach ($methods as $methodkey => $meta) {
            $label   = (string) $meta['label'];
            $tooltip = (string) $meta['tooltip'];
            $account = (string) ($bymethod[$methodkey] ?? '');
            $available = $account !== '';

            $cbname  = "ifthenpay_enable_{$methodkey}";
            $accname = "ifthenpay_account_{$methodkey}";

            // Available means the gateway key binds an account for it; checked means the admin
            // turned it on. Only a checked method may be the default, so the two are tracked apart.
            $ischecked = $available && !empty($saved[$cbname]);

            // The row's controls sit in a grid with the method name in a neighbouring cell, so they
            // have no <label> of their own. Without an explicit name a screen reader announces only
            // "checkbox" with no indication of which method it belongs to.
            $checkbox = $mform->createElement('advcheckbox', $cbname, '', '', [
                'aria-label' => get_string('form:enable_method', 'paygw_ifthenpay', $label),
            ]);
            $mform->setDefault($cbname, $ischecked ? 1 : 0);

            // A constant white tile behind the mark, as on the settings page: these are
            // third-party logos drawn in dark ink, and bg-white is one of the few Bootstrap
            // colours that stays fixed in either colour mode.
            $logo = $meta['image'] !== ''
                ? \html_writer::span(
                    \html_writer::empty_tag('img', [
                        'src' => $meta['image'],
                        'alt' => '',
                        'class' => 'ifthenpay-method-logo',
                    ]),
                    'bg-white rounded d-inline-flex align-items-center p-1 me-2 mr-2'
                )
                : '';

            // Account, or an actionable explanation when the method is not on this gateway key.
            // The id lets the AMD module swap this text when the Gateway Key changes.
            $accounttext = \html_writer::span(
                $available ? s($account) : get_string('form:method_not_activated', 'paygw_ifthenpay'),
                $available ? 'small' : 'small text-muted',
                ['id' => 'ifthenpay-account-' . $methodkey]
            );

            $radio = $mform->createElement('radio', 'ifthenpay_defaultmethod', '', '', $methodkey, [
                'aria-label' => get_string('form:set_default_method', 'paygw_ifthenpay', $label),
            ]);

            if (!$available) {
                $checkbox->updateAttributes(['disabled' => 'disabled']);
            }

            /*
             * Two independent reasons a method cannot be the default. Pay-by-Link only defines a
             * selected_method code for some methods — Google Pay and Apple Pay have none — and
             * beyond that, a method must be switched on. The AMD module keeps the second in step
             * as checkboxes toggle; the first never changes.
             */
            $canbedefault = in_array($methodkey, $preselectable, true);
            if (!$canbedefault) {
                $radio->updateAttributes([
                    'disabled' => 'disabled',
                    'title' => get_string('form:default_unsupported', 'paygw_ifthenpay'),
                ]);
            } else if (!$ischecked) {
                $radio->updateAttributes(['disabled' => 'disabled']);
            }

            $group = $mform->addGroup(
                [
                    $mform->createElement('html', '<div class="col-1 px-0">'),
                    $checkbox,
                    $mform->createElement('html', '</div><div class="col-4 px-0 d-flex align-items-center">'
                        . $logo . \html_writer::span(s($label), '', ['title' => $tooltip]) . '</div>'),
                    $mform->createElement('html', '<div class="col-5 px-0">' . $accounttext . '</div>'),
                    $mform->createElement('html', '<div class="col-2 px-0">'),
                    $radio,
                    $mform->createElement('html', '</div>'),
                ],
                "ifthenpay_row_{$methodkey}",
                '',
                '',
                false
            );
            $group->setAttributes(['class' => 'row align-items-center w-100 m-0']);

            // Value carrier: the account is derived, never chosen, so it submits hidden.
            $mform->addElement('hidden', $accname, $available ? $account : '');
            $mform->setType($accname, PARAM_RAW_TRIMMED);
        }

        $mform->addElement(
            'radio',
            'ifthenpay_defaultmethod',
            get_string('form:default_method', 'paygw_ifthenpay'),
            get_string('form:default_method_none', 'paygw_ifthenpay'),
            ''
        );
        $mform->setType('ifthenpay_defaultmethod', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('ifthenpay_defaultmethod', 'form:default_method', 'paygw_ifthenpay');

        // Fall back to "no default" if the saved method can no longer be one, otherwise the row
        // would show a checked but disabled radio while "None" looked unset.
        $default = (string) ($saved['ifthenpay_defaultmethod'] ?? '');
        $keepdefault = isset($methods[$default]) && in_array($default, $preselectable, true);
        $mform->setDefault('ifthenpay_defaultmethod', $keepdefault ? $default : '');
    }

    /**
     * Optional checkout description.
     *
     * @param \MoodleQuickForm $mform Form.
     * @param array<string, mixed> $saved Saved config.
     * @return void
     */
    private static function render_description(\MoodleQuickForm $mform, array $saved): void {
        $mform->addElement(
            'text',
            'ifthenpay_description',
            get_string('form:description', 'paygw_ifthenpay'),
            ['size' => 64, 'maxlength' => self::DESCRIPTION_MAX_LENGTH]
        );
        $mform->setType('ifthenpay_description', PARAM_TEXT);
        $mform->addRule(
            'ifthenpay_description',
            get_string('form:error_maxchars', 'paygw_ifthenpay', self::DESCRIPTION_MAX_LENGTH),
            'maxlength',
            self::DESCRIPTION_MAX_LENGTH,
            'client'
        );
        if (!empty($saved['ifthenpay_description'])) {
            $mform->setDefault('ifthenpay_description', $saved['ifthenpay_description']);
        }
        $mform->addHelpButton('ifthenpay_description', 'form:description', 'paygw_ifthenpay');
    }

    /**
     * Validate the account gateway configuration form.
     *
     * Every error must be keyed to an element this form actually registered: Moodle silently
     * discards an error attached to an unknown name, refusing the save while rendering nothing,
     * which leaves the admin staring at an unchanged form.
     *
     * On success, registers the webhook callback for the selected gateway key.
     *
     * Everything needed is in the submission — the hidden ifthenpay_state field mirrors the whole
     * visible form — so the form wrapper itself is not a parameter here.
     *
     * @param \stdClass $data   Raw form submission data.
     * @param array<string, string> $errors Errors to attach to form fields (by ref).
     * @return void
     */
    public static function validate(\stdClass $data, array &$errors): void {
        /*
         * Nothing was configurable, so there is nothing to validate and no callback to register.
         * The one decision the form still offers is whether the gateway stays switched on, and it
         * must not: customers could choose ifthenpay and then fail at checkout. Switching it off is
         * allowed, and preserves the stored configuration through render_unavailable()'s hidden
         * fields.
         *
         * Gated on the live status rather than on which fields arrived, because those hidden fields
         * include ifthenpay_state and would otherwise look like a normal submission.
         */
        if (\paygw_ifthenpay_backoffice_key_status() !== 'ok') {
            if (!empty($data->enabled)) {
                $errors['enabled'] = get_string('form:error_unavailable_enable', 'paygw_ifthenpay');
            }
            return;
        }

        $raw = $data->ifthenpay_state ?? null;
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state)) {
            $errors['ifthenpay_methods_note'] = get_string('form:error_state_missing', 'paygw_ifthenpay');
            return;
        }

        $methods     = $state['methods'] ?? [];
        $default     = (string) ($state['defaultmethod'] ?? '');
        $description = (string) ($state['description'] ?? '');

        if (!array_filter($methods, fn ($m) => !empty($m['enabled']))) {
            $errors['ifthenpay_methods_note'] = get_string('form:error_no_methods_enabled', 'paygw_ifthenpay');
        }

        if ($default !== '') {
            if (!isset($methods[$default])) {
                $errors['ifthenpay_defaultmethod'] = get_string('form:error_default_unknown', 'paygw_ifthenpay', $default);
            } else if (empty($methods[$default]['enabled'])) {
                $errors['ifthenpay_defaultmethod'] = get_string('form:error_default_not_enabled', 'paygw_ifthenpay', $default);
            }
        }

        // Re-checked server-side: the client rule only constrains the visible field, and the
        // state arrives as PARAM_RAW.
        if (\core_text::strlen($description) > self::DESCRIPTION_MAX_LENGTH) {
            $errors['ifthenpay_description'] =
                get_string('form:error_maxchars', 'paygw_ifthenpay', self::DESCRIPTION_MAX_LENGTH);
        }

        if (!empty($errors)) {
            return;
        }

        // The state arrives as a hidden PARAM_RAW field, so nothing guarantees it carries a gateway
        // key even after the checks above.
        $gatewaykey = (string) ($state['gatewaykey'] ?? '');
        if ($gatewaykey === '') {
            $errors['ifthenpay_gatewaykey'] = get_string('form:error_state_missing', 'paygw_ifthenpay');
            return;
        }

        try {
            \paygw_ifthenpay_api()->activate_callback_by_gateway_context($gatewaykey);
        } catch (\moodle_exception $e) {
            $errors['ifthenpay_methods_note'] =
                get_string('form:error_callback_activation', 'paygw_ifthenpay', $e->getMessage());
        }
    }

    /**
     * Build initial JSON state from saved config (for defaults only).
     *
     * @param array<string, mixed> $methods   Methods meta, in display order (keys are used).
     * @param array<string, mixed> $saved     Saved config.
     * @param string               $currentgk Current gateway key.
     * @return array<string, mixed> State array to be JSON-encoded.
     */
    private static function build_initial_state(array $methods, array $saved, string $currentgk): array {
        $state = [
            'gatewaykey'    => $currentgk,
            'defaultmethod' => isset($saved['ifthenpay_defaultmethod']) ? (string) $saved['ifthenpay_defaultmethod'] : '',
            'description'   => isset($saved['ifthenpay_description']) ? (string) $saved['ifthenpay_description'] : '',
            'methods'       => [],
        ];

        foreach (array_keys($methods) as $methodkey) {
            $cbname  = "ifthenpay_enable_{$methodkey}";
            $selname = "ifthenpay_account_{$methodkey}";
            $state['methods'][$methodkey] = [
                'enabled' => !empty($saved[$cbname]),
                'account' => isset($saved[$selname]) ? (string) $saved[$selname] : '',
            ];
        }
        return $state;
    }

    /**
     * Get saved persistent config as an array (safe).
     *
     * @param account_gateway $form The account gateway form wrapper.
     * @return array<string, mixed> Saved config array (empty array if none/invalid).
     */
    private static function get_saved_config(account_gateway $form): array {
        $persist = $form->get_gateway_persistent();
        if (!$persist) {
            return [];
        }
        $json = $persist->get('config');
        if (!is_string($json) || $json === '') {
            return [];
        }
        $arr = json_decode($json, true);
        return is_array($arr) ? $arr : [];
    }
}
