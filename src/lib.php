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
 * Shared functions for the ifthenpay payment gateway.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use paygw_ifthenpay\local\api_client;
use paygw_ifthenpay\local\data_formatter;

/**
 * Get the configured Ifthenpay Backoffice Key.
 *
 * @return string Backoffice key or empty string.
 */
function paygw_ifthenpay_get_backoffice_key(): string {
    return trim((string) get_config('paygw_ifthenpay', 'backoffice_key'));
}

/**
 * Build an API client using the stored Backoffice Key.
 *
 * @return api_client
 * @throws \moodle_exception If the Backoffice Key is missing.
 */
function paygw_ifthenpay_api(): api_client {
    $key = paygw_ifthenpay_get_backoffice_key();
    if ($key === '') {
        throw new \moodle_exception('error_missing_backoffice_key', 'paygw_ifthenpay');
    }
    return new api_client($key, 8);
}

/**
 * Current state of the configured Backoffice Key, as ifthenpay sees it.
 *
 * A key can be saved and later revoked, and CLI writes bypass the admin setting's validation
 * entirely — so "configured" is not the same as "working". Two endpoints are needed because
 * neither answers both questions: the entity/subentity service says whether the key belongs to a
 * client (403 if not), and /gateway/get says whether that client has Moodle Gateway Keys.
 *
 * Transport failures report 'ok': an unreachable API says nothing about the key, and an outage
 * should not accuse an admin of a misconfiguration they do not have.
 *
 * @return string One of 'unconfigured', 'rejected', 'nomoodlekeys', 'ok'.
 */
function paygw_ifthenpay_backoffice_key_status(): string {
    if (paygw_ifthenpay_get_backoffice_key() === '') {
        return 'unconfigured';
    }
    try {
        if (!paygw_ifthenpay_api()->verify_backoffice_key()) {
            return 'rejected';
        }
    } catch (\moodle_exception $e) {
        return $e->errorcode === 'api:error_unauthorized' ? 'rejected' : 'ok';
    } catch (\Throwable $e) {
        return 'ok';
    }

    // Shares the memoised fetch with the gateway form. Null means the lookup failed, which is not
    // evidence of a misconfiguration, so it reports 'ok' like any other transport failure.
    $dataset = paygw_ifthenpay_gateway_dataset();
    if ($dataset === null) {
        return 'ok';
    }
    return $dataset['gatewaykeys'] === [] ? 'nomoodlekeys' : 'ok';
}

/**
 * Detect preferred language (pt|en|es|fr) from Moodle environment.
 *
 * @return string Two-letter language code.
 */
function paygw_ifthenpay_detect_language(): string {
    $lang = substr((string) current_language(), 0, 2);
    return in_array($lang, ['pt', 'en', 'es', 'fr'], true) ? $lang : 'pt';
}

/**
 * Decode a serialized ifthenpay_state JSON string from gateway config.
 *
 * @param array<string, mixed> $cfg Gateway configuration array (expects 'ifthenpay_state').
 * @return stdClass Decoded state object (empty object if absent/invalid).
 */
function paygw_ifthenpay_decode_state(array $cfg): stdClass {
    $raw = $cfg['ifthenpay_state'] ?? '';
    if (!is_string($raw) || trim($raw) === '') {
        return new stdClass();
    }
    $decoded = json_decode($raw);
    return (is_object($decoded) && json_last_error() === JSON_ERROR_NONE) ? $decoded : new stdClass();
}

/**
 * Fetch and format the gateway dataset: keys plus their per-method accounts.
 *
 * Cached for the request via the Moodle Cache API, keyed by Backoffice Key. A single form render
 * asks for this more than once — the key-status check and the form build both need it — and the
 * answer is the same each time. It is deliberately not cached beyond the request: it reflects the
 * merchant's own backoffice configuration, which an admin may change and expect to see at once.
 *
 * Returns null rather than an empty dataset when the fetch fails, so callers can tell "this
 * account has no gateway keys" apart from "we could not find out".
 *
 * @return array{gatewaykeys: array<string, string>, accounts: array<string, array<string, array<string, string>>>}|null
 *         Null if the dataset could not be fetched.
 */
function paygw_ifthenpay_gateway_dataset(): ?array {
    $key = paygw_ifthenpay_get_backoffice_key();
    if ($key === '') {
        return null;
    }

    $cache = cache::make('paygw_ifthenpay', 'gatewaydata');
    $cached = $cache->get($key);
    if ($cached !== false) {
        // A failed lookup is stored as null, so distinguish it from a cache miss.
        return $cached === null ? null : $cached;
    }

    try {
        $dataset = data_formatter::format_gateway_dataset(paygw_ifthenpay_api()->get_gateway_keys());
    } catch (\Throwable $e) {
        debugging('[ifthenpay] gateway_dataset: ' . $e->getMessage(), DEBUG_DEVELOPER);
        $dataset = null;
    }
    $cache->set($key, $dataset);

    return $dataset;
}


/**
 * Fetch available methods as a map methodKey => meta.
 * Meta keys: position (int), image (string), tooltip (string), label (string).
 *
 * @return array<string, MethodMeta> Methods map.
 */
function paygw_ifthenpay_get_methods_rich(): array {
    // Cached across requests: this is ifthenpay's global catalog, the same for every site, so
    // refetching it on each admin page render is pure latency. See db/caches.php for the TTL.
    $cache = cache::make('paygw_ifthenpay', 'methods');
    $methods = $cache->get('available');
    if ($methods !== false) {
        return $methods;
    }

    try {
        $methods = data_formatter::format_available_payment_methods(api_client::get_available_payment_methods());
    } catch (\Throwable $e) {
        debugging('[ifthenpay] get_methods_rich: ' . $e->getMessage(), DEBUG_DEVELOPER);
        return [];
    }
    $cache->set('available', $methods);

    return $methods;
}

/**
 * Whether ifthenpay recognises a Backoffice Key.
 *
 * Takes the key as an argument rather than reading configuration, because the admin setting needs
 * to check a value the user has just typed and not yet saved.
 *
 * @param string $key Backoffice Key to check.
 * @param int $timeout Request timeout in seconds.
 * @return bool True if the key belongs to an ifthenpay client.
 * @throws \moodle_exception 'api:error_unauthorized' if refused; transport/JSON errors otherwise.
 */
function paygw_ifthenpay_key_is_recognised(string $key, int $timeout = 5): bool {
    return (new api_client($key, $timeout))->verify_backoffice_key();
}

/**
 * Payment methods that Pay-by-Link can preselect at checkout.
 *
 * Wrapped here rather than read from data_formatter directly: phpstan-moodle emits phantom
 * errors when a plugin class file references another plugin class in this namespace. See the
 * note in phpstan.neon.
 *
 * @return string[] Method keys.
 */
function paygw_ifthenpay_preselectable_methods(): array {
    return data_formatter::PRESELECTABLE_METHODS;
}

/**
 * Build the full admin dataset in two requests.
 *
 * The accounts come from the same /gateway/get response as the keys — each row carries them
 * inline — so no per-gateway-key request is needed.
 *
 * @return array{
 *     gatewaykeys: array<string, string>,
 *     methods: array<string, MethodMeta>,
 *     accounts: array<string, array<string, string>>
 * }
 */
function paygw_ifthenpay_build_full_admin_dataset(): array {
    $dataset = paygw_ifthenpay_gateway_dataset() ?? ['gatewaykeys' => [], 'accounts' => []];
    $methods = paygw_ifthenpay_get_methods_rich();

    // Keep only methods this plugin actually supports. A gateway key can carry accounts for
    // methods that are not offered here (Cofidis and Bizum both appear in the payload), and the
    // catalog's IsVisible flag is the single authority on what is offered. Filtering here rather
    // than at each consumer keeps phantom methods out of the injected JS dataset, and so out of
    // the submitted state.
    $dataset['accounts'] = array_map(
        static fn(array $bymethod): array => array_intersect_key($bymethod, $methods),
        $dataset['accounts']
    );
    $dataset['methods'] = $methods;

    return $dataset;
}

/**
 * Fetch a transaction by its token.
 *
 * Missing is not an error: callers decide what to do with null.
 *
 * @param string $token Internal order token.
 * @param string $fields Fields to select.
 * @return stdClass|null The record, or null when there is no such token.
 */
function paygw_ifthenpay_tx_get(string $token, string $fields = '*'): ?stdClass {
    global $DB;
    return $DB->get_record('paygw_ifthenpay_tx', ['token' => $token], $fields, IGNORE_MISSING) ?: null;
}


/**
 * Record the ifthenpay transaction id against a transaction.
 *
 * @param int $id Transaction row id.
 * @param string $txid Transaction id reported by ifthenpay.
 * @return void
 */
function paygw_ifthenpay_tx_set_transaction_id(int $id, string $txid): void {
    global $DB;
    // Both columns in one statement: two set_field calls would be two UPDATEs for one change.
    $DB->update_record('paygw_ifthenpay_tx', (object) [
        'id'             => $id,
        'transaction_id' => $txid,
        'timemodified'   => time(),
    ]);
}

/**
 * Move a transaction to a final unpaid state, without ever overwriting a paid one.
 *
 * The "not paid" test is part of the UPDATE rather than a separate read, so a webhook settling the
 * payment concurrently cannot be overwritten.
 *
 * @param int $id Transaction row id.
 * @param string $state 'CANCELED' or 'ERROR'.
 * @return void
 */
function paygw_ifthenpay_tx_set_unpaid_state(int $id, string $state): void {
    global $DB;
    $DB->execute(
        'UPDATE {paygw_ifthenpay_tx} SET state = ?, timemodified = ? WHERE id = ? AND state <> ?',
        [$state, time(), $id, 'PAID']
    );
}

/**
 * Create the PENDING transaction for a checkout attempt.
 *
 * @param array<string, mixed> $fields Record fields, excluding timestamps and state.
 * @return void
 */
function paygw_ifthenpay_tx_create(array $fields): void {
    global $DB;
    $now = time();
    $DB->insert_record('paygw_ifthenpay_tx', (object) ($fields + [
        'timecreated'    => $now,
        'timemodified'   => $now,
        'paymentid'      => null,
        'transaction_id' => null,
        'state'          => 'PENDING',
    ]));
}

/**
 * Report a problem on the payment path so it cannot pass unnoticed.
 *
 * debugging() alone is a no-op on a production site, and this is the only record of a customer
 * having paid without receiving their order, so it is also logged as an event where an
 * administrator can find and report on it. The token identifies the transaction; the gateway key
 * is the webhook's shared secret and is never logged.
 *
 * @param string $token Internal order token.
 * @param string $problem What went wrong.
 * @return void
 */
function paygw_ifthenpay_report_payment_problem(string $token, string $problem): void {
    debugging('[ifthenpay] payment ' . $token . ': ' . $problem, DEBUG_DEVELOPER);

    \paygw_ifthenpay\event\payment_problem::create([
        'context' => \context_system::instance(),
        'other' => ['token' => $token, 'problem' => $problem],
    ])->trigger();
}

/**
 * Process an Ifthenpay webhook event (idempotent).
 *
 * Contract (same as webhook.php GET):
 *  - $token  Internal order token (repository primary key).
 *  - $amount Amount as stored (string); must match exactly.
 *  - $apk    Base64 of the gateway key; must decode to rec->gateway_key.
 *
 * Behaviour:
 *  - Returns true when the payment is (or becomes) PAID.
 *  - Returns false on any validation failure or technical error.
 *
 * @param string $token  Payment token.
 * @param string $amount Amount string (already formatted).
 * @param string $apk    Base64-encoded gateway key.
 * @return bool True if processed/paid, false otherwise.
 */
function paygw_ifthenpay_process_webhook(string $token, string $amount, string $apk): bool {
    global $DB;

    /*
     * Serialise per token. ifthenpay retries webhooks, and a retry can arrive while the first is
     * still in flight; without a lock both would pass the "already paid" test, insert a payment
     * row each and deliver the order twice.
     */
    $lock = \core\lock\lock_config::get_lock_factory('paygw_ifthenpay')->get_lock($token, 10);
    if (!$lock) {
        debugging('[ifthenpay] webhook ' . $token . ': could not acquire lock', DEBUG_DEVELOPER);
        return false;
    }

    try {
        // Read inside the lock: anything read before it may already be stale.
        $rec = paygw_ifthenpay_tx_get($token);
        if (!$rec) {
            return false;
        }

        // The anti-phishing key and the amount must match the record this site created.
        $decoded = base64_decode($apk, true);
        if ($decoded === false || $decoded !== (string) $rec->gateway_key) {
            return false;
        }
        if ($amount !== (string) $rec->amount) {
            debugging('[ifthenpay] webhook ' . $token . ': amount mismatch', DEBUG_DEVELOPER);
            return false;
        }

        // Idempotent: a retry of an already-settled payment is a success, not a repeat.
        if ((string) $rec->state === 'PAID') {
            return true;
        }

        /*
         * Record the payment first, then deliver, then mark PAID.
         *
         * The payment id is persisted the moment it exists, so a failure later cannot lose track of
         * money that has been captured. The transaction is only marked PAID once delivery has
         * actually succeeded, which leaves a failed delivery in PENDING with its payment id already
         * stored: the next webhook retry skips save_payment (the id is there) and re-attempts
         * delivery instead of short-circuiting on PAID and abandoning the customer.
         */
        $paymentid = (int) ($rec->paymentid ?? 0);
        if (!$paymentid) {
            $paymentid = \core_payment\helper::save_payment(
                (int) $rec->accountid,
                (string) $rec->component,
                (string) $rec->paymentarea,
                (int) $rec->itemid,
                (int) $rec->userid,
                (float) $rec->amount,
                (string) $rec->currency,
                'ifthenpay'
            );
            $DB->update_record('paygw_ifthenpay_tx', (object) [
                'id'           => $rec->id,
                'paymentid'    => $paymentid,
                'timemodified' => time(),
            ]);
        }

        // A component signals "could not deliver" by returning false, so the result is checked
        // rather than assumed; returning false here asks ifthenpay to retry.
        $delivered = \core_payment\helper::deliver_order(
            $rec->component,
            $rec->paymentarea,
            $rec->itemid,
            $paymentid,
            $rec->userid
        );
        if ($delivered === false) {
            paygw_ifthenpay_report_payment_problem($token, 'payment captured but delivery was refused');
            return false;
        }

        $DB->update_record('paygw_ifthenpay_tx', (object) [
            'id'           => $rec->id,
            'state'        => 'PAID',
            'timemodified' => time(),
        ]);

        return true;
    } catch (\Throwable $e) {
        paygw_ifthenpay_report_payment_problem($token, $e->getMessage());
        return false;
    } finally {
        $lock->release();
    }
}
