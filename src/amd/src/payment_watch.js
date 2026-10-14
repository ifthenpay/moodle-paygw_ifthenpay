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
 * Waiting for a payment to reach a final state, by polling return.php?action=verify.
 *
 * Shared by the return page and by the gateways modal while the checkout runs in its own tab.
 *
 * @module     paygw_ifthenpay/payment_watch
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/* eslint-disable promise/always-return */

define([], function() {
  "use strict";

  /**
   * Ask the server until the payment reaches a final state.
   *
   * The wait between checks grows with the time already waited, from 1s to 5s: a payment usually
   * confirms within seconds, and a slow one should not hammer the server. Checks right away when
   * the page becomes visible again, since browsers stretch timers in background tabs to a minute
   * or more.
   *
   * @param {String} url The verify URL.
   * @param {Number} firstDelayMs Wait before the first check.
   * @param {Number} maxWaitMs Give up after this long, reporting UNCONFIRMED.
   * @param {Function} onDone Called once with PAID, CANCELED, ERROR, NOTFOUND or UNCONFIRMED.
   * @returns {Function} Stops the watching.
   */
  const watch = function(url, firstDelayMs, maxWaitMs, onDone) {
    const startedAt = Date.now();
    let timer = null;
    let inflight = false;
    let stopped = false;

    const outcome = function(data) {
      if (!data) {
        return null; // A failed check just means asking again.
      }
      if (data.state && data.state !== "PENDING") {
        return data.state;
      }
      // A JSON error with no state (e.g. the session lapsed) cannot be fixed by asking again, but
      // the payment may still land, so it is reported as unconfirmed rather than as a failure.
      return data.error || Date.now() - startedAt >= maxWaitMs ? "UNCONFIRMED" : null;
    };

    const check = function() {
      // One request at a time: becoming visible mid-request must not start a second chain.
      if (inflight || stopped) {
        return;
      }
      inflight = true;
      window.clearTimeout(timer);
      fetch(url, {credentials: "same-origin"})
        .then((response) => response.json())
        .catch(() => null)
        .then(function(data) {
          inflight = false;
          if (stopped) {
            return;
          }
          const result = outcome(data);
          if (result) {
            stop();
            onDone(result);
            return;
          }
          const waited = Date.now() - startedAt;
          timer = window.setTimeout(check, Math.min(Math.max(waited / 10, 1000), 5000));
        });
    };

    const onVisible = function() {
      if (!document.hidden) {
        check();
      }
    };

    const stop = function() {
      stopped = true;
      window.clearTimeout(timer);
      document.removeEventListener("visibilitychange", onVisible);
    };

    document.addEventListener("visibilitychange", onVisible);
    timer = window.setTimeout(check, firstDelayMs);
    return stop;
  };

  return {watch: watch};
});
