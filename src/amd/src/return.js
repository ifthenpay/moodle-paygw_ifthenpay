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
 * Interactivity for the post-checkout return page.
 *
 * @module     paygw_ifthenpay/return
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(["paygw_ifthenpay/payment_watch", "paygw_ifthenpay/checkout_tab"], function(PaymentWatch, CheckoutTab) {
  "use strict";

  // Stop polling after this long. The webhook still confirms the order afterwards; this only
  // bounds how long the page keeps asking.
  const MAX_WAIT_MS = 90000;

  /**
   * AMD entry point: wait for the payment, then move on.
   *
   * The verify, success and courses URLs arrive on window.ifthenpay via data_for_js.
   *
   * @param {{spinner: string, status: string, retry: string}} selectors Element ids.
   * @param {{timeout: string}} i18n Message for when the wait ends without a payment.
   */
  const init = function(selectors, i18n) {
    const ds = window.ifthenpay || {};
    const spinner = document.getElementById(selectors.spinner);
    const status = document.getElementById(selectors.status);
    const retry = document.getElementById(selectors.retry);
    if (!ds.verifyUrl || !spinner || !status || !retry) {
      return;
    }

    // Toggles Bootstrap's d-none rather than writing an inline display value, so the theme keeps
    // control of the spinner's layout. The explanation line is left alone while waiting: it is
    // the card's live region, and rewriting it on every check would repeat it to a screen reader.
    const setBusy = function(busy) {
      spinner.classList.toggle("d-none", !busy);
      retry.disabled = busy;
    };

    const start = function() {
      setBusy(true);
      PaymentWatch.watch(ds.verifyUrl, 0, MAX_WAIT_MS, function(state) {
        if (state === "PAID") {
          if (ds.tab) {
            // Checkout tab: the Moodle page that opened it continues to the success URL itself.
            CheckoutTab.close(ds.successUrl);
          } else {
            window.location.assign(ds.successUrl);
          }
          return;
        }
        // The payment is not lost: the webhook still completes the order, and a Multibanco
        // customer may legitimately be hours away from that. So the card says so and releases
        // them, rather than leaving a stalled spinner behind.
        setBusy(false);
        status.textContent = i18n.timeout;
      });
    };

    retry.addEventListener("click", function(e) {
      e.preventDefault();
      start();
    });
    start();
  };

  return {init: init};
});
