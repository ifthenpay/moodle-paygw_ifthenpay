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
 * This module is responsible for ifthenpay content in the gateways modal.
 *
 * By default the browser leaves for the ifthenpay checkout in this tab. When the administrator has
 * chosen "new tab" (window.ifthenpay_checkout, supplied by the hook callback), the checkout opens in
 * its own tab and this page stays here, polling for the outcome. Core_payment turns the promise
 * returned by process() into the rest: resolving it shows the message and goes to the success URL,
 * rejecting it shows the message as an alert.
 *
 * @module     paygw_ifthenpay/gateway_modal
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable promise/always-return */
/* eslint-disable no-empty-function */

define(["core/templates", "core/modal", "core/modal_events", "core/str", "paygw_ifthenpay/payment_watch"], function(
  Templates,
  Modal,
  ModalEvents,
  Str,
  PaymentWatch
) {
  // A named target, so a second "open the checkout" reuses the tab instead of piling up new ones.
  const TAB_NAME = "ifthenpay_checkout";
  // A Multibanco customer may take a while; past this the webhook still completes the order.
  const MAX_WAIT_MS = 15 * 60 * 1000;

  const endpoint = function(file) {
    // eslint-disable-next-line no-undef
    return M.cfg.wwwroot + "/payment/gateway/ifthenpay/" + file;
  };

  // URLSearchParams encodes every value: an item named "Maths & Physics" would otherwise break the
  // query string apart, and a "#" would truncate everything after it.
  const payUrl = function(params, component, paymentArea, itemId, description) {
    return endpoint("pay.php?" + new URLSearchParams({
      ...params,
      component: component,
      paymentarea: paymentArea,
      itemid: itemId,
      description: description,
    }));
  };

  const sameTab = function(...payment) {
    return Modal.create({
      body: Templates.render("paygw_ifthenpay/ifthenpay_button_placeholder", {}),
    }).then(function(modal) {
      modal.show();
      window.location.href = payUrl({}, ...payment);
      // Never resolves: the modal stays up until the browser leaves for ifthenpay.
      return new Promise(function() {});
    });
  };

  // The messages shown when the wait ends, fetched in the browser so that no other page carries them.
  const MESSAGE_KEYS = ["paid", "canceled", "error", "timeout", "start_failed"];
  const loadMessages = function() {
    return Str.get_strings(MESSAGE_KEYS.map((key) => ({key: "process:checkout_" + key, component: "paygw_ifthenpay"})))
      .then((list) => Object.fromEntries(MESSAGE_KEYS.map((key, i) => [key, list[i]])));
  };

  const newTab = function(component, paymentArea, itemId, description) {
    // Opened now, while the click still counts as a user gesture, and pointed at the checkout once
    // its URL exists. Opening it after the request would be blocked as a pop-up.
    const tab = window.open("", TAB_NAME);
    if (!tab) {
      return sameTab(component, paymentArea, itemId, description);
    }

    return new Promise(function(resolve, reject) {
      // What each final state tells core_payment: resolving shows the message and goes to the
      // success URL, rejecting shows it as an alert.
      const outcomes = (msg) => ({
        PAID: [resolve, msg.paid],
        CANCELED: [reject, msg.canceled],
        ERROR: [reject, msg.error],
        NOTFOUND: [reject, msg.error],
        UNCONFIRMED: [reject, msg.timeout],
      });
      // If the strings cannot load, the wait still ends: an empty message beats a dialog that never closes.
      const messages = loadMessages().catch(() => ({}));
      let modal = null;
      let closed = false;
      let checkoutUrl = "";
      let stopWatching = function() {};

      // The checkout tab is left alone here: its own return pages close it, and closing it from this
      // side could cut off a confirmation screen the customer is still reading.
      const settle = function(settler, message) {
        stopWatching();
        modal.hide();
        settler(message);
      };

      const start = function() {
        const created = fetch(payUrl({format: "json"}, component, paymentArea, itemId, description),
          {credentials: "same-origin"})
          .then((response) => response.json());
        Promise.all([created, messages])
          .then(function([data, msg]) {
            if (!data || !data.url || !data.token) {
              throw new Error("no checkout url");
            }
            checkoutUrl = data.url;
            if (tab.closed) {
              window.open(checkoutUrl, TAB_NAME);
            } else {
              tab.location.href = checkoutUrl;
            }
            modal.getRoot()[0].querySelector('[data-region="ifthenpay-reopen"]')
              ?.classList.remove("d-none");
            // The dialog may have been closed while the checkout was being created: nothing is waiting.
            if (!closed) {
              const verifyUrl = endpoint("return.php?action=verify&token=" + encodeURIComponent(data.token));
              stopWatching = PaymentWatch.watch(verifyUrl, 2000, MAX_WAIT_MS, (state) => settle(...outcomes(msg)[state]));
            }
          })
          .catch(function() {
            // Always the localised sentence: a server error message can be technical, and the alert
            // core_payment raises from it renders its text as HTML.
            tab.close();
            messages.then((msg) => settle(reject, msg.start_failed));
          });
      };

      Modal.create({
        body: Templates.render("paygw_ifthenpay/checkout_waiting", {}),
        removeOnClose: true,
      }).then(function(created) {
        modal = created;
        const root = modal.getRoot();
        // Closing the dialog only stops the waiting. The payment itself carries on, and the webhook
        // still enrols the customer, so there is no message to raise.
        root.on(ModalEvents.hidden, function() {
          closed = true;
          stopWatching();
        });
        root.on("click", '[data-action="paygw_ifthenpay/reopen"]', function(e) {
          e.preventDefault();
          if (checkoutUrl) {
            window.open(checkoutUrl, TAB_NAME);
          }
        });
        modal.show();
        start();
      });
    });
  };

  const process = function(component, paymentArea, itemId, description) {
    return window.ifthenpay_checkout?.newtab
      ? newTab(component, paymentArea, itemId, description)
      : sameTab(component, paymentArea, itemId, description);
  };

  return {process: process};
});
