<?php
// This file is part of Moodle - https://moodle.org/.
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

namespace paygw_ifthenpay\privacy;

use stdClass;
use context_system;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for paygw_ifthenpay.
 *
 * {@see collection::add_external_location_link()} is deliberately not called: nothing personal
 * leaves the site. The outbound payloads carry a random token, an amount, a non-personal
 * description, a language code, configured account identifiers and return URLs.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_payment\privacy\paygw_provider,
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\userlist_provider
{
    /**
     * Describe the personal data this plugin stores.
     *
     * gateway_key is deliberately absent. It is merchant configuration, identical for every user,
     * so it is not personal data — and it is the shared secret the webhook authenticates with
     * (the anti-phishing key is base64(gateway_key)). Exporting it would let any data subject
     * settle arbitrary pending payments for this merchant.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored in this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('paygw_ifthenpay_tx', [
            'userid'         => 'privacy:metadata:ifthenpay_tx:userid',
            'timecreated'    => 'privacy:metadata:ifthenpay_tx:timecreated',
            'timemodified'   => 'privacy:metadata:ifthenpay_tx:timemodified',
            'token'          => 'privacy:metadata:ifthenpay_tx:token',
            'component'      => 'privacy:metadata:ifthenpay_tx:component',
            'paymentarea'    => 'privacy:metadata:ifthenpay_tx:paymentarea',
            'itemid'         => 'privacy:metadata:ifthenpay_tx:itemid',
            'accountid'      => 'privacy:metadata:ifthenpay_tx:accountid',
            'amount'         => 'privacy:metadata:ifthenpay_tx:amount',
            'currency'       => 'privacy:metadata:ifthenpay_tx:currency',
            'redirect_url'   => 'privacy:metadata:ifthenpay_tx:redirect_url',
            'transaction_id' => 'privacy:metadata:ifthenpay_tx:transaction_id',
            'paymentid'      => 'privacy:metadata:ifthenpay_tx:paymentid',
            'state'          => 'privacy:metadata:ifthenpay_tx:state',
        ], 'privacy:metadata:ifthenpay_tx');

        return $collection;
    }

    /**
     * Gets the list of contexts that contain user information for the given user.
     *
     * @param int $userid The user to search.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $list = new contextlist();
        if ($DB->record_exists('paygw_ifthenpay_tx', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }

    /**
     * Exports user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $sysctx = context_system::instance();
        if (!in_array($sysctx->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $userid = $contextlist->get_user()->id;
        $records = $DB->get_records('paygw_ifthenpay_tx', ['userid' => $userid], 'timecreated ASC');
        if (!$records) {
            return;
        }

        $w = writer::with_context($sysctx);
        foreach ($records as $r) {
            $subpath = ['ifthenpay', (string)$r->id];
            $w->export_data($subpath, (object)[
                'timecreated'    => transform::datetime($r->timecreated),
                'timemodified'   => transform::datetime($r->timemodified),
                'token'          => $r->token,
                'component'      => $r->component,
                'paymentarea'    => $r->paymentarea,
                'itemid'         => $r->itemid,
                'accountid'      => $r->accountid,
                'amount'         => $r->amount,
                'currency'       => $r->currency,
                'redirect_url'   => $r->redirect_url,
                'transaction_id' => $r->transaction_id,
                'paymentid'      => $r->paymentid,
                'state'          => $r->state,
            ]);
        }
    }

    /**
     * Deletes user data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $sysctx = context_system::instance();
        if (!in_array($sysctx->id, $contextlist->get_contextids(), true)) {
            return;
        }

        $DB->delete_records('paygw_ifthenpay_tx', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Deletes all users' data in the specified context.
     *
     * @param \context $context The context to delete in.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel === CONTEXT_SYSTEM) {
            $DB->delete_records('paygw_ifthenpay_tx');
        }
    }

    /**
     * Adds users who have data in the given context.
     *
     * @param userlist $userlist The userlist to populate.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }
        $sql = "SELECT DISTINCT userid FROM {paygw_ifthenpay_tx} WHERE userid IS NOT NULL";
        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Deletes data for multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        if ($userlist->get_context()->contextlevel !== CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        [$in, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $DB->delete_records_select('paygw_ifthenpay_tx', "userid $in", $params);
    }

    /**
     * Exports gateway data for a specific payment (called by core_payment).
     *
     * @param \context $context Context of the payment.
     * @param array<int, string> $subcontext Subpath within the context to export under.
     * @param stdClass $payment The core payment record.
     * @return void
     */
    public static function export_payment_data(\context $context, array $subcontext, stdClass $payment): void {
        global $DB;

        $tx = $DB->get_record('paygw_ifthenpay_tx', ['paymentid' => $payment->id], '*', IGNORE_MISSING);
        if (!$tx) {
            return;
        }

        writer::with_context($context)->export_data(
            array_merge($subcontext, ['ifthenpay']),
            (object)[
                'timecreated'    => transform::datetime($tx->timecreated),
                'timemodified'   => transform::datetime($tx->timemodified),
                'token'          => $tx->token,
                'amount'         => $tx->amount,
                'currency'       => $tx->currency,
                'state'          => $tx->state,
                'transaction_id' => $tx->transaction_id,
                'redirect_url'   => $tx->redirect_url,
            ]
        );
    }

    /**
     * Deletes gateway data for the given payments (called by core_payment).
     *
     * @param string $paymentsql SQL selecting payment.id of the payments.
     * @param array<array-key, mixed> $paymentparams Params for $paymentsql.
     * @return void
     */
    public static function delete_data_for_payment_sql(string $paymentsql, array $paymentparams): void {
        global $DB;

        $DB->delete_records_select('paygw_ifthenpay_tx', "paymentid IN ($paymentsql)", $paymentparams);
    }
}
