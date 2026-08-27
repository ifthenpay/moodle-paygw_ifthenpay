<?php
// This file is part of Moodle - https://moodle.org/.
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
 * Ifthenpay payment gateway – admin form integration.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay;

use core_payment\form\account_gateway;

/**
 * What core_payment asks of this plugin.
 *
 * Building and validating the configuration form is delegated to \paygw_ifthenpay\local\config_form,
 * so this class stays a readable statement of the contract.
 */
final class gateway extends \core_payment\gateway
{
    /**
     * Supported currencies.
     *
     * @return string[] List of ISO currency codes.
     */
    public static function get_supported_currencies(): array {
        return ['EUR'];
    }

    /**
     * Whether refunds are supported.
     *
     * @return bool False (not supported).
     */
    public static function supports_refunds(): bool {
        return false;
    }

    /**
     * Add configuration fields to the gateway form.
     *
     * Path: Site admin → Payments → Payment accounts → (Account) → Gateways → ifthenpay → Configure.
     *
     * @param account_gateway $form The account gateway form wrapper.
     * @return void
     */
    public static function add_configuration_to_gateway_form(account_gateway $form): void {
        \paygw_ifthenpay\local\config_form::build($form);
    }

    /**
     * Validate the submitted configuration.
     *
     * @param account_gateway $form   Configuration form wrapper.
     * @param \stdClass       $data   Raw form submission data.
     * @param array<string, mixed>  $files  Uploaded files (unused).
     * @param array<string, string> $errors Errors to attach to form fields (by ref).
     * @return void
     */
    public static function validate_gateway_form(
        account_gateway $form,
        \stdClass $data,
        array $files,
        array &$errors
    ): void {
        \paygw_ifthenpay\local\config_form::validate($data, $errors);
    }
}
