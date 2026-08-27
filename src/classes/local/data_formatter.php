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
 * Helpers to format data for the ifthenpay integration.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\local;

use stdClass;

/**
 * Formatters and payload constructors for the ifthenpay integration.
 *
 * Pure formatting and payload building: nothing here performs HTTP of its own.
 */
class data_formatter
{
    /**
     * Fields on a /gateway/get row that describe the key itself rather than a payment method.
     *
     * Every other field is a method, whose value is that method's account ('' when the method is
     * not activated for the key).
     */
    private const GATEWAY_METADATA_FIELDS = ['Alias', 'GatewayKey', 'Gateway_Tipo', 'Tipo', 'VatNumber'];

    /** @var int Hard cap the Pay-by-Link API enforces on the description field. */
    private const API_DESCRIPTION_MAX_LENGTH = 200;

    /**
     * Methods that Pay-by-Link can preselect at checkout, i.e. those with a selected_method code.
     *
     * The API defines codes for Multibanco, MB WAY, Payshop, Credit Card and PIX only; Google Pay
     * and Apple Pay have none and cannot be a default. Kept as an allowlist rather than a list of
     * exclusions so that a method added to ifthenpay's catalog later is simply not preselectable
     * until it is added here — failing closed instead of sending an unsupported code.
     */
    public const PRESELECTABLE_METHODS = ['MB', 'MBWAY', 'PAYSHOP', 'CCARD', 'PIX'];

    /**
     * Format the /gateway/get response into gateway keys and their per-method accounts.
     *
     * One pass yields both halves, because the response already contains both: each row carries an
     * account per method inline (e.g. "MBWAY": "MBWAY | ZWZ-568360", "" when not activated), which
     * is byte-identical to the 'Conta' that GetAccountsByGatewayKey would return for the same key.
     * Deriving them here is what lets the gateway form load with two requests instead of one per
     * gateway key plus two.
     *
     * The method key comes from the value, not the field name: the text before ' | ' is the
     * Entidade, bucketed by the same rule used for Multibanco's numeric entities. Field names do
     * not match method keys ('Multibanco' here vs 'MB' from /gateway/methods/available), so
     * deriving from the value avoids maintaining a translation table that could silently rot.
     *
     * @param array<array-key, mixed> $raw Response from api_client::get_gateway_keys().
     * @return array{gatewaykeys: array<string, string>, accounts: array<string, array<string, string>>}
     *         'gatewaykeys' maps GatewayKey => Alias; 'accounts' maps GatewayKey => method => account.
     */
    public static function format_gateway_dataset(array $raw): array {
        $keys = [];
        $accounts = [];

        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $alias = (string) ($row['Alias'] ?? '');
            $gk = (string) ($row['GatewayKey'] ?? '');
            if ($alias === '' || $gk === '') {
                continue;
            }
            $keys[$gk] = $alias;
            $accounts[$gk] = [];

            foreach ($row as $field => $value) {
                if (in_array($field, self::GATEWAY_METADATA_FIELDS, true)) {
                    continue;
                }
                $conta = is_string($value) ? trim($value) : '';
                if ($conta === '') {
                    // Method exists in the payload but is not activated for this gateway key.
                    continue;
                }
                $method = self::account_method_key($conta);
                if ($method === '') {
                    continue;
                }
                $accounts[$gk][$method] = $conta;
            }
        }

        return ['gatewaykeys' => $keys, 'accounts' => $accounts];
    }

    /**
     * Derive a payment method key from an account string such as "MB | HLP-000001" or "11687 | 991".
     *
     * Numeric entities are Multibanco, which the API expresses as an entity/subentity pair rather
     * than a named prefix.
     *
     * @param string $conta Account string.
     * @return string Method key, or '' if it cannot be determined.
     */
    private static function account_method_key(string $conta): string {
        $entity = trim(explode('|', $conta, 2)[0]);
        if ($entity === '') {
            return '';
        }
        return is_numeric($entity) ? 'MB' : $entity;
    }

    /**
     * Format available payment methods into [key => ['position','image','tooltip','label']].
     *
     * Input is the response from api_client::get_available_payment_methods().
     *
     * @param array<array-key, mixed> $raw Method rows from the API.
     * @return array<string, MethodMeta> Map keyed by method entity.
     */
    public static function format_available_payment_methods(array $raw): array {
        $methods = [];
        foreach ($raw as $entry) {
            $key = $entry['Entity'] ?? '';
            if ($key === '' || !($entry['IsVisible'] ?? false)) {
                continue;
            }
            $methods[$key] = [
                'position' => (int)($entry['Position'] ?? 0),
                'image'    => (string)($entry['SmallImageUrl'] ?? ''),
                'tooltip'  => (string)($entry['DescriptionEN'] ?? ''),
                'label'    => (string)($entry['Method'] ?? ''),
            ];
        }
        uasort($methods, fn ($a, $b) => $a['position'] <=> $b['position']);
        return $methods;
    }

    /**
     * Format a monetary amount with two decimals (e.g., "12.34").
     *
     * @param float $amount Amount to format.
     * @return string Amount formatted as "0.00".
     */
    public static function format_amount(float $amount): string {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Build the payload to create an ifthenpay Pay-by-Link.
     *
     * The methods and accounts on offer were already constrained by the admin form.
     *
     * @param float     $cost          Raw payment amount.
     * @param \stdClass $state         Decoded ifthenpay_state (gateway form state).
     * @param string    $token         Unique token for this payment attempt.
     * @param string    $desccheckout  Optional checkout description.
     * @return array<string, mixed> Payload for api_client::create_pay_by_link().
     */
    public static function build_pay_by_link_payload(
        float $cost,
        stdClass $state,
        string $token,
        string $desccheckout
    ): array {
        // The [TRANSACTIONID] placeholder stays literal: ifthenpay substitutes it on the way back.
        $success = (new \moodle_url('/payment/gateway/ifthenpay/return.php', [
            'token' => $token,
        ]))->out(false) . '&txid=[TRANSACTIONID]';

        $cancel = (new \moodle_url('/payment/gateway/ifthenpay/cancel.php', [
            'token' => $token,
            'type'  => 'CANCEL',
        ]))->out(false);

        $error = (new \moodle_url('/payment/gateway/ifthenpay/cancel.php', [
            'token' => $token,
            'type'  => 'ERROR',
        ]))->out(false);

        $payload = [
            'id'          => $token,
            'amount'      => self::format_amount($cost),
            'description' => self::make_description($state, $token, $desccheckout),
            'lang'        => \paygw_ifthenpay_detect_language(),
            'accounts'    => self::make_accounts($state),
            'success_url' => $success,
            'cancel_url'  => $cancel,
            'error_url'   => $error,
            // One payment per link. Each checkout attempt mints its own link, so a link that has
            // been paid should never be payable again — this states that rather than relying on
            // it being the default. The schema types it as the string "true", not a boolean.
            'otp'         => 'true',
        ];

        $index = self::compute_selected_method($state);
        if ($index !== null) {
            $payload['selected_method'] = $index;
        }

        return $payload;
    }

    /**
     * Compose a human-friendly description.
     *
     * Priority: state.description → checkout description → “Order #<token>”.
     *
     * @param stdClass $state         Decoded ifthenpay_state.
     * @param string   $token         Payment token.
     * @param string   $desccheckout Optional checkout description.
     * @return string
     */
    private static function make_description(stdClass $state, string $token, string $desccheckout): string {
        $suffix = trim((string) ($state->description ?? '')) ?: trim($desccheckout);
        $description = 'Order #' . $token . ($suffix !== '' ? ' - ' . $suffix : '');

        /*
         * The Pay-by-Link API caps description at 200 characters. The gateway form limits its own
         * field to 150, but the checkout description comes from the payable item (a course name,
         * say) and is not bounded anywhere, so a long one would be rejected at payment time.
         * Truncated with core_text so multibyte names are not cut mid-character.
         */
        return \core_text::strlen($description) > self::API_DESCRIPTION_MAX_LENGTH
            ? \core_text::substr($description, 0, self::API_DESCRIPTION_MAX_LENGTH)
            : $description;
    }

    /**
     * Build the ifthenpay accounts string.
     *
     * Includes only enabled methods, preserving the JSON order.
     * Example: "MB|ADC-663833;MBWAY|MBW-123456".
     *
     * @param stdClass $state Decoded ifthenpay_state (methods as stdClass).
     * @return string
     */
    private static function make_accounts(stdClass $state): string {
        $parts = [];
        foreach ($state->methods as $meta) {
            if (!empty($meta->enabled) && !empty($meta->account)) {
                // Normalize spaces around the pipe, e.g. "MB | ADC-663833" → "MB|ADC-663833".
                $parts[] = preg_replace('/\s*\|\s*/', '|', trim((string)$meta->account));
            }
        }
        return implode(';', $parts);
    }

    /**
     * Resolve the payment method code to preselect at checkout.
     *
     * Pay-by-Link's selected_method is a global method code, not an offset into the accounts
     * string: per the API schema, 1 = MULTIBANCO, 2 = MB WAY, 3 = PAYSHOP, 4 = CREDIT CARD,
     * 8 = PIX. Those are exactly the Position values returned by /gateway/methods/available, so
     * the catalog's position is passed straight through.
     *
     * Returns null when no default is set ("None") or the key is unknown.
     *
     * @param stdClass $state Decoded ifthenpay_state.
     * @return int|null Method code, or null when no default applies.
     */
    private static function compute_selected_method(stdClass $state): ?int {
        $target = isset($state->defaultmethod) ? (string)$state->defaultmethod : '';
        if ($target === '' || !in_array($target, self::PRESELECTABLE_METHODS, true)) {
            return null;
        }

        $methodsrich = paygw_ifthenpay_get_methods_rich();
        if (!isset($methodsrich[$target])) {
            return null;
        }

        return (int)$methodsrich[$target]['position'];
    }
}
