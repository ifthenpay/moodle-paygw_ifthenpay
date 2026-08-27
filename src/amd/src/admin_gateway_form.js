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

define(["jquery"], function($) {
    "use strict";

    /**
     * Find a form element by its name, preferring Moodle's generated id.
     *
     * @param {String} name Element name.
     * @returns {jQuery} Matching element(s), possibly empty.
     */
    const byName = function(name) {
        const $byId = $("#id_" + name);
        return $byId.length ? $byId : $('[name="' + name + '"]');
    };

    /**
     * Form controller.
     *
     * @param {Object} selectors Field names supplied by PHP.
     */
    const Form = function(selectors) {
        const data = window.ifthenpay || {};
        this.s = selectors;
        this.accounts = data.accounts || {};
        this.notActivated = data.notActivated || "";
        // Methods Pay-by-Link can preselect; the rest never get an enabled default radio.
        this.preselectable = data.preselectable || [];
        this.$gatewayKey = byName(this.s.gatewayKey);
        // The rows PHP rendered, in the same order. Not derived from the accounts map: a method
        // activated on no Gateway Key at all still has a row that must stay in the state.
        this.methods = data.methods || [];
    };

    /**
     * The account bound to a method under the currently selected Gateway Key.
     *
     * @param {String} method Method key.
     * @returns {String} Account id, or an empty string when the method is not activated.
     */
    Form.prototype.accountFor = function(method) {
        const forKey = this.accounts[this.$gatewayKey.val()] || {};
        return forKey[method] || "";
    };

    /**
     * Allow a method to be the default only while it is switched on.
     *
     * Being bound to an account is not enough: an available but unchecked method would otherwise
     * be selectable as the default and then rejected on save. Methods with no Pay-by-Link
     * selected_method code (Google Pay, Apple Pay) can never be the default at all.
     *
     * @param {String} method Method key.
     * @param {Boolean} available Whether the current Gateway Key binds an account for it.
     * @returns {void}
     */
    Form.prototype.gateDefault = function(method, available) {
        const $default = $('[name="' + this.s.defaultMethod + '"][value="' + method + '"]');
        const allowed = available
            && this.preselectable.indexOf(method) !== -1
            && byName(this.s.enablePrefix + method).is(":checked");

        $default.prop("disabled", !allowed);
        if (!allowed && $default.prop("checked")) {
            $default.prop("checked", false);
            $('[name="' + this.s.defaultMethod + '"][value=""]').prop("checked", true);
        }
    };

    /**
     * Apply the selected Gateway Key to every method row.
     *
     * @returns {void}
     */
    Form.prototype.refresh = function() {
        this.methods.forEach(function(method) {
            const account = this.accountFor(method);
            const available = account !== "";
            const $enable = byName(this.s.enablePrefix + method);

            byName(this.s.accountPrefix + method).val(account);

            // The account id is data and must be escaped; the not-activated notice is markup (it
            // carries a support link), so it is assigned as HTML.
            const $cell = $("#ifthenpay-account-" + method);
            if (available) {
                $cell.text(account);
            } else {
                $cell.html(this.notActivated);
            }
            $cell.toggleClass("text-muted", !available);

            $enable.prop("disabled", !available);
            if (!available) {
                $enable.prop("checked", false);
            }
            this.gateDefault(method, available);
        }.bind(this));

        // Nothing selectable at all: the key only carries methods this plugin does not support.
        // Toggled with d-none rather than jQuery's .toggle(), which writes an inline display value
        // and would override whatever the theme sets on the element.
        const usable = this.methods.some(function(method) {
            return this.accountFor(method) !== "";
        }.bind(this));
        $("#ifthenpay-no-methods").toggleClass("d-none", usable);

        this.syncState();
    };

    /**
     * Mirror the visible form into the hidden state field.
     *
     * @returns {void}
     */
    Form.prototype.syncState = function() {
        const state = {
            gatewaykey: this.$gatewayKey.val() || "",
            defaultmethod: $('[name="' + this.s.defaultMethod + '"]:checked').val() || "",
            description: byName(this.s.description).val() || "",
            methods: {}
        };

        this.methods.forEach(function(method) {
            state.methods[method] = {
                enabled: byName(this.s.enablePrefix + method).is(":checked"),
                account: byName(this.s.accountPrefix + method).val() || ""
            };
        }.bind(this));

        byName(this.s.state).val(JSON.stringify(state));
    };

    /**
     * Bind listeners and apply the initial state.
     *
     * @returns {void}
     */
    Form.prototype.start = function() {
        this.$gatewayKey.on("change", this.refresh.bind(this));

        const sync = this.syncState.bind(this);
        this.methods.forEach(function(method) {
            byName(this.s.enablePrefix + method).on("change", function() {
                this.gateDefault(method, this.accountFor(method) !== "");
                sync();
            }.bind(this));
        }.bind(this));
        $('[name="' + this.s.defaultMethod + '"]').on("change", sync);
        byName(this.s.description).on("input", sync);

        this.refresh();
    };

    return {
        /**
         * Entry point.
         *
         * @param {Object} selectors Field names supplied by PHP.
         * @returns {void}
         */
        init: function(selectors) {
            new Form(selectors).start();
        }
    };
});
