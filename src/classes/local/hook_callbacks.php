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

namespace paygw_ifthenpay\local;

use core\hook\output\before_standard_head_html_generation;

/**
 * Hook callbacks for the ifthenpay payment gateway.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Tell the gateways modal how to open the checkout.
     *
     * core_payment calls the gateway's JavaScript with the item being paid for and nothing else, and
     * the browser only lets a tab be opened synchronously from the click. So the choice cannot be
     * fetched when the customer clicks; it has to already be on the page. Nothing is added unless
     * the administrator has chosen the new tab.
     *
     * @param before_standard_head_html_generation $hook
     * @return void
     */
    public static function before_standard_head_html_generation(before_standard_head_html_generation $hook): void {
        global $PAGE;

        if (during_initial_install() || (string) get_config('paygw_ifthenpay', 'checkout_target') !== 'newtab') {
            return;
        }
        if (!in_array('ifthenpay', \core\plugininfo\paygw::get_enabled_plugins() ?? [], true)) {
            return;
        }

        $component = 'paygw_ifthenpay';
        $PAGE->requires->data_for_js('ifthenpay_checkout', (object) [
            'newtab' => true,
            'strings' => (object) [
                'waiting' => get_string('process:checkout_waiting', $component),
                'reopen' => get_string('process:checkout_reopen', $component),
                'paid' => get_string('process:checkout_paid', $component),
                'canceled' => get_string('process:checkout_canceled', $component),
                'error' => get_string('process:checkout_error', $component),
                'timeout' => get_string('process:checkout_timeout', $component),
                'startfailed' => get_string('process:checkout_start_failed', $component),
            ],
        ]);
    }
}
