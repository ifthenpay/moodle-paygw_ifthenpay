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

/**
 * Cache definitions for the ifthenpay payment gateway.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [

    /*
     * ifthenpay's catalog of supported payment methods (/gateway/methods/available).
     *
     * Global, identical for every site, and it changes when ifthenpay adds a method — not between
     * page loads. Without this, every admin settings page and every gateway form render blocks on
     * the same remote request. A TTL keeps a newly launched method appearing on its own.
     */
    'methods' => [
        'mode' => cache_store::MODE_APPLICATION,
        'simplekeys' => true,
        'simpledata' => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 1,
        'ttl' => 3600,
    ],

    /*
     * Gateway keys and their per-method accounts, per Backoffice Key.
     *
     * Request-scoped on purpose: this reflects the merchant's own configuration, which an admin
     * may change in the ifthenpay backoffice and expect to see on the next page load. Caching it
     * only for the request still removes the repeat fetches within a single render.
     */
    'gatewaydata' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => false,
        'simpledata' => false,
    ],
];
