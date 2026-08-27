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

define([], function() {
  "use strict";

  // Stop polling after this long. The webhook still confirms the order afterwards; this only
  // bounds how long the page keeps asking.
  const MAX_WAIT_MS = 90000;

  /**
   * Get an element by id.
   *
   * @param {string} id
   */
  function $(id) {
    return document.getElementById(id);
  }

  /**
   * Toggle the disabled state of the retry button.
   *
   * @param {HTMLElement} btn
   * @param {boolean} yes
   */
  function disable(btn, yes) {
    if (!btn) {
      return;
    }
    if (yes) {
      btn.setAttribute("disabled", "disabled");
      btn.classList.add("disabled");
    } else {
      btn.removeAttribute("disabled");
      btn.classList.remove("disabled");
    }
  }

  class IfthenpayReturn {
    /**
     * @param {{selectors:Object, i18n:Object}} cfg
     * @param {{verifyUrl:string, successUrl:string, coursesUrl:string}} ds
     */
    constructor(cfg, ds) {
      this.s = cfg.selectors || {};
      this.t = cfg.i18n || {};
      this.ds = ds || {};

      this.spinner = $(this.s.spinner);
      this.status = $(this.s.status);
      this.retry = $(this.s.retry);

      this.busy = false;
      this.attempt = 0;
      this.elapsed = 0;
    }

    /**
     * Start polling and wire the retry button.
     */
    init() {
      if (!this.ds.verifyUrl || !this.retry || !this.spinner || !this.status) {
        return;
      }
      // Keep asking until the payment lands or we give up, so the order confirms as soon as
      // ifthenpay knows about it rather than waiting for the webhook.
      this.poll();

      this.retry.addEventListener("click", (e) => {
        e.preventDefault();
        if (this.busy) {
          return;
        }
        this.attempt = 0;
        this.poll();
      });
    }

    /**
     * Delay before the next attempt, in milliseconds.
     *
     * Backs off from 1s to 5s: a payment usually confirms within the first few seconds, so the
     * early checks are close together, while a slow one stops hammering the status API.
     *
     * @param {number} attempt Zero-based attempt number already made.
     * @returns {number} Milliseconds to wait.
     */
    delayFor(attempt) {
      return Math.min(1000 * (attempt + 1), 5000);
    }

    /**
     * Check now, then schedule the next check until PAID or the deadline passes.
     */
    async poll() {
      if (await this.verifyOnce()) {
        return;
      }

      this.elapsed += this.delayFor(this.attempt);
      if (this.elapsed >= MAX_WAIT_MS) {
        // The webhook still completes the order on its own; say so rather than stopping silently.
        this.giveUp();
        return;
      }

      const wait = this.delayFor(this.attempt);
      this.attempt += 1;
      window.setTimeout(() => this.poll(), wait);
    }

    /**
     * Set busy state (spinner + disable retry button).
     *
     * Toggles Bootstrap's d-none rather than writing an inline display value, so the theme keeps
     * control of the spinner's layout. The explanation line is left alone: it is the card's live
     * region, and rewriting it on every poll would repeat the same sentence to a screen reader.
     *
     * @param {boolean} on
     */
    setBusy(on) {
      this.busy = !!on;
      if (this.spinner) {
        this.spinner.classList.toggle("d-none", !on);
      }
      disable(this.retry, on);
    }

    /**
     * Say what happens next once polling has given up.
     *
     * The payment is not lost — the webhook still completes the order — and a customer paying by
     * Multibanco may legitimately be hours away from that, so the card says so and releases them
     * from the page rather than leaving a stalled spinner behind.
     *
     * @returns {void}
     */
    giveUp() {
      this.setBusy(false);
      if (this.status && this.t.timeout) {
        this.status.textContent = this.t.timeout;
      }
    }

    /**
     * Ask the server once whether the payment has landed.
     *
     * @returns {Promise<boolean>} True when paid (and the redirect has been started).
     */
    async verifyOnce() {
      this.setBusy(true);
      try {
        const res = await fetch(this.ds.verifyUrl, {
          credentials: "same-origin",
        });
        const data = await res.json();
        if (data && data.paid) {
          window.location.assign(this.ds.successUrl);
          return true;
        }
      } catch {
        /* Ignore: a failed check just means we try again. */
      }
      return false;
    }
  }

  /**
   * AMD entry point.
   *
   * @param {Object} selectors
   * @param {Object} i18n
   */
  function init(selectors, i18n) {
    const dataset = window.ifthenpay || {};
    const app = new IfthenpayReturn({selectors, i18n}, dataset);
    app.init();
  }

  return {init};
});
