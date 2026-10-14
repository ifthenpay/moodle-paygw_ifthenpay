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

define(["core/templates", "core/modal", "core/modal_events"], function(
  Templates,
  Modal,
  ModalEvents
) {
  // A named target, so a second "open the checkout" reuses the tab instead of piling up new ones.
  const TAB_NAME = "ifthenpay_checkout";
  const POLL_FIRST_MS = 2000;
  const POLL_MAX_MS = 5000;
  // A Multibanco customer may take a while; past this the webhook still completes the order.
  const MAX_WAIT_MS = 15 * 60 * 1000;

  const endpoint = function(file) {
    // eslint-disable-next-line no-undef
    return M.cfg.wwwroot + "/payment/gateway/ifthenpay/" + file;
  };

  const showModalWithPlaceholder = function() {
    return Modal.create({
      body: Templates.render(
        "paygw_ifthenpay/ifthenpay_button_placeholder",
        {}
      ),
    }).then(function(modal) {
      modal.show();
    });
  };

  // Every value is encoded: an item named "Maths & Physics" would otherwise break the query
  // string apart, and a "#" would truncate everything after it.
  const payQuery = function(component, paymentArea, itemId, description) {
    return "component=" + encodeURIComponent(component)
      + "&paymentarea=" + encodeURIComponent(paymentArea)
      + "&itemid=" + encodeURIComponent(itemId)
      + "&description=" + encodeURIComponent(description);
  };

  const sameTab = function(component, paymentArea, itemId, description) {
    return showModalWithPlaceholder().then(function() {
      window.location.href = endpoint("pay.php?")
        + payQuery(component, paymentArea, itemId, description);
      // Never resolves: the modal stays up until the browser leaves for ifthenpay.
      return new Promise(function() {});
    });
  };

  const newTab = function(settings, component, paymentArea, itemId, description) {
    const strings = settings.strings || {};

    // Opened now, while the click still counts as a user gesture, and pointed at the checkout once
    // its URL exists. Opening it after the request would be blocked as a pop-up.
    const tab = window.open("", TAB_NAME);
    if (!tab) {
      return sameTab(component, paymentArea, itemId, description);
    }

    return new Promise(function(resolve, reject) {
      let modal = null;
      let stopped = false;
      let checkoutUrl = "";

      const closeTab = function() {
        try {
          if (!tab.closed) {
            tab.close();
          }
        } catch {
          // Nothing to do: a tab we cannot close is one the customer can.
        }
      };

      const finish = function(settle, message, closeCheckout) {
        stopped = true;
        if (closeCheckout) {
          closeTab();
        }
        if (modal) {
          modal.hide();
        }
        settle(message);
      };

      const poll = function(token, waited) {
        if (stopped) {
          return;
        }
        const delay = Math.min(POLL_FIRST_MS + waited / 10, POLL_MAX_MS);
        window.setTimeout(function() {
          if (stopped) {
            return;
          }
          fetch(endpoint("return.php?action=verify&token=" + encodeURIComponent(token)),
            {credentials: "same-origin"})
            .then(function(response) {
              return response.json();
            })
            // A failed check just means asking again.
            .catch(function() {
              return null;
            })
            .then(function(data) {
              if (stopped) {
                return;
              }
              const state = data && data.state;
              // The checkout tab's own return pages close it, so it is left alone here: closing it
              // from this side could cut off a confirmation screen the customer is still reading.
              if (state === "PAID") {
                finish(resolve, strings.paid, false);
              } else if (state === "CANCELED") {
                finish(reject, strings.canceled, false);
              } else if (state === "ERROR" || state === "NOTFOUND") {
                finish(reject, strings.error, false);
              } else if (data && data.error) {
                // A JSON error with no state, e.g. the session lapsed. Asking again cannot help, but
                // the payment may still land, so say that rather than calling it a failure.
                finish(reject, strings.timeout, false);
              } else if (waited + delay >= MAX_WAIT_MS) {
                finish(reject, strings.timeout, false);
              } else {
                poll(token, waited + delay);
              }
            });
        }, delay);
      };

      const start = function() {
        fetch(endpoint("pay.php?format=json&") + payQuery(component, paymentArea, itemId, description),
          {credentials: "same-origin"})
          .then(function(response) {
            return response.json();
          })
          .then(function(data) {
            if (!data || !data.url || !data.token) {
              throw new Error(strings.startfailed);
            }
            checkoutUrl = data.url;
            if (tab.closed) {
              window.open(checkoutUrl, TAB_NAME);
            } else {
              tab.location.href = checkoutUrl;
            }
            const reopen = modal.getRoot()[0].querySelector('[data-region="ifthenpay-reopen"]');
            if (reopen) {
              reopen.classList.remove("d-none");
            }
            poll(data.token, 0);
          })
          .catch(function() {
            // Always the localised sentence: a server error message can be technical, and the alert
            // core_payment raises from it renders its text as HTML.
            finish(reject, strings.startfailed, true);
          });
      };

      Modal.create({
        body: Templates.render("paygw_ifthenpay/checkout_waiting", {
          message: strings.waiting,
          reopenlabel: strings.reopen,
        }),
        removeOnClose: true,
      }).then(function(created) {
        modal = created;
        const root = modal.getRoot();
        // Closing the dialog only stops the waiting. The payment itself carries on, and the webhook
        // still enrols the customer, so there is no message to raise.
        root.on(ModalEvents.hidden, function() {
          stopped = true;
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
    const settings = window.ifthenpay_checkout;
    if (settings && settings.newtab) {
      return newTab(settings, component, paymentArea, itemId, description);
    }
    return sameTab(component, paymentArea, itemId, description);
  };

  return {process: process};
});
