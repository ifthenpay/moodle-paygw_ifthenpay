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
 * Closing the browser tab the ifthenpay checkout ran in.
 *
 * The tab is opened by script (paygw_ifthenpay/gateways_modal), so the browser lets script close it
 * again. When it does not — the customer opened the link some other way — the page stays as it is
 * and, where a fallback is given, carries on there.
 *
 * @module     paygw_ifthenpay/checkout_tab
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {
  "use strict";

  // Closing is not instant: window.closed is only reliable after the browser has had a moment.
  const CLOSE_GRACE_MS = 400;

  /**
   * Try to close this tab.
   *
   * @param {string} [fallbackUrl] Where to go if the tab cannot be closed.
   * @returns {void}
   */
  function close(fallbackUrl) {
    window.close();
    window.setTimeout(function() {
      if (!window.closed && fallbackUrl) {
        window.location.assign(fallbackUrl);
      }
    }, CLOSE_GRACE_MS);
  }

  return {close: close};
});
