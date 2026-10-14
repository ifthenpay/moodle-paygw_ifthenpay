// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Gateway configuration form behaviour for the ifthenpay payment account.
 *
 * A Gateway Key binds at most one account per payment method, so there is nothing to choose:
 * changing the Gateway Key swaps each method's read-only account text and enables or disables its
 * row. Methods with no account on the selected key cannot be enabled or made the default.
 *
 * The hidden ifthenpay_state field mirrors the current UI so the server validates what the admin
 * actually sees. The accounts map arrives on window.ifthenpay via data_for_js, since Moodle caps
 * js_call_amd arguments at 1024 characters.
 *
 * @module     paygw_ifthenpay/admin_gateway_form
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


define([], function() {
    "use strict";

    /**
     * Entry point.
     *
     * @param {Object} s Field names supplied by PHP.
     * @returns {void}
     */
    const init = function(s) {
        const data = window.ifthenpay || {};
        const accounts = data.accounts || {};
        // The rows PHP rendered, in the same order. Not derived from the accounts map: a method
        // activated on no Gateway Key at all still has a row that must stay in the state.
        const methods = data.methods || [];
        // Methods Pay-by-Link can preselect; the rest never get an enabled default radio.
        const preselectable = data.preselectable || [];

        // Moodle's generated id where there is one; hidden fields have none, so by name.
        const field = (name) => document.getElementById("id_" + name) || document.querySelector(`[name="${name}"]`);
        const defaultRadio = (value) => document.querySelector(`[name="${s.defaultMethod}"][value="${value}"]`);
        const gatewayKey = field(s.gatewayKey);
        const enable = (method) => field(s.enablePrefix + method);
        // The account bound to a method under the selected Gateway Key, or "" when not activated.
        const accountFor = (method) => (accounts[gatewayKey.value] || {})[method] || "";

        /**
         * Mirror the visible form into the hidden state field.
         */
        const syncState = function() {
            const checked = document.querySelector(`[name="${s.defaultMethod}"]:checked`);
            const state = {
                gatewaykey: gatewayKey.value || "",
                defaultmethod: checked ? checked.value : "",
                description: field(s.description).value || "",
                methods: {},
            };
            methods.forEach((method) => {
                state.methods[method] = {
                    enabled: enable(method).checked,
                    account: field(s.accountPrefix + method).value || "",
                };
            });
            field(s.state).value = JSON.stringify(state);
        };

        /**
         * Allow a method to be the default only while it is switched on.
         *
         * Being bound to an account is not enough: an available but unchecked method would otherwise
         * be selectable as the default and then rejected on save. Methods with no Pay-by-Link
         * selected_method code (Google Pay, Apple Pay) can never be the default at all.
         *
         * @param {String} method Method key.
         */
        const gateDefault = function(method) {
            const radio = defaultRadio(method);
            radio.disabled = !(accountFor(method) !== "" && preselectable.includes(method) && enable(method).checked);
            if (radio.disabled && radio.checked) {
                defaultRadio("").checked = true;
            }
        };

        /**
         * Apply the selected Gateway Key to every method row.
         */
        const refresh = function() {
            methods.forEach((method) => {
                const account = accountFor(method);
                const available = account !== "";
                field(s.accountPrefix + method).value = account;

                // The account id is data and must be escaped; the not-activated notice is markup (it
                // carries a support link), so it is assigned as HTML.
                const cell = document.getElementById("ifthenpay-account-" + method);
                if (available) {
                    cell.textContent = account;
                } else {
                    cell.innerHTML = data.notActivated || "";
                }
                cell.classList.toggle("text-muted", !available);

                enable(method).disabled = !available;
                if (!available) {
                    enable(method).checked = false;
                }
                gateDefault(method);
            });

            // Nothing selectable at all: the key only carries methods this plugin does not support.
            document.getElementById("ifthenpay-no-methods")
                .classList.toggle("d-none", methods.some((method) => accountFor(method) !== ""));

            syncState();
        };

        gatewayKey.addEventListener("change", refresh);
        methods.forEach((method) => enable(method).addEventListener("change", () => {
            gateDefault(method);
            syncState();
        }));
        document.querySelectorAll(`[name="${s.defaultMethod}"]`).forEach((radio) => radio.addEventListener("change", syncState));
        field(s.description).addEventListener("input", syncState);

        refresh();
    };

    return {init: init};
});
