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
use paygw_ifthenpay\local\notification_recipients;

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
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider
{
    /**
     * The personal-data columns of paygw_ifthenpay_tx: described, and exported, field for field.
     *
     * gateway_key is deliberately absent. It is merchant configuration, identical for every user,
     * so it is not personal data — and it is the shared secret the webhook authenticates with
     * (the anti-phishing key is base64(gateway_key)). Exporting it would let any data subject
     * settle arbitrary pending payments for this merchant.
     */
    private const FIELDS = [
        'userid', 'timecreated', 'timemodified', 'token', 'component', 'paymentarea', 'itemid', 'accountid',
        'amount', 'currency', 'redirect_url', 'transaction_id', 'paymentid', 'state',
    ];

    /**
     * Describe the personal data this plugin stores.
     *
     * The staff-notification recipients chosen for a payment account are stored by this plugin in
     * the account's gateway configuration, the config column of core_payment's payment_gateways
     * table. core_payment's own provider does not know about them (the column is opaque gateway
     * configuration to it), so this plugin describes that column as a database table it stores
     * data in, and exports and deletes it itself. The notifications themselves go through
     * core_message. When one cannot be sent, the notification_failed event names the recipient in
     * relateduserid, which the logstore's own privacy provider covers.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored in this plugin.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'paygw_ifthenpay_tx',
            array_combine(self::FIELDS, array_map(fn($f) => 'privacy:metadata:ifthenpay_tx:' . $f, self::FIELDS)),
            'privacy:metadata:ifthenpay_tx'
        );
        $collection->add_database_table(
            'payment_gateways',
            ['config' => 'privacy:metadata:payment_gateways:config'],
            'privacy:metadata:payment_gateways'
        );
        $collection->link_subsystem('core_message', 'privacy:metadata:core_message');

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

        $contextids = [];
        foreach (self::recipient_accounts(null, $userid) as $account) {
            $contextids[$account->contextid] = $account->contextid;
        }
        if ($contextids) {
            /** @var \moodle_database $db */
            $db = $DB;
            /** @var array{0: string, 1: array<string, mixed>} $insql */
            $insql = $db->get_in_or_equal(array_values($contextids), SQL_PARAMS_NAMED);
            $list->add_from_sql("SELECT id FROM {context} WHERE id {$insql[0]}", $insql[1]);
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

        self::export_recipient_data($contextlist);

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
            $w->export_data(['ifthenpay', (string) $r->id], self::export_record($r));
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

        $userid = $contextlist->get_user()->id;
        if (is_numeric($userid)) {
            foreach ($contextlist->get_contextids() as $contextid) {
                self::remove_recipients((int) $contextid, [(int) $userid]);
            }
        }

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

        self::remove_recipients((int) $context->id, null);

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
        $userids = [];
        foreach (self::recipient_accounts((int) $userlist->get_context()->id, null) as $account) {
            foreach ($account->userids as $id) {
                $userids[$id] = $id;
            }
        }
        $userlist->add_users(array_values($userids));

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

        if ($userlist->get_userids()) {
            self::remove_recipients((int) $userlist->get_context()->id, array_map('intval', $userlist->get_userids()));
        }

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

        writer::with_context($context)->export_data(array_merge($subcontext, ['ifthenpay']), self::export_record($tx));
    }

    /**
     * One transaction as exported: the described fields, with timestamps made readable.
     *
     * The userid is left out, since the export already belongs to that user.
     *
     * @param stdClass $tx Transaction record.
     * @return stdClass Export data.
     */
    private static function export_record(stdClass $tx): stdClass {
        $data = array_intersect_key((array) $tx, array_flip(array_diff(self::FIELDS, ['userid'])));
        $data['timecreated'] = transform::datetime($tx->timecreated);
        $data['timemodified'] = transform::datetime($tx->timemodified);
        return (object) $data;
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

    /**
     * Exports the payment accounts the user receives notifications for, per approved context.
     *
     * @param approved_contextlist $contextlist Approved contexts and user.
     * @return void
     */
    private static function export_recipient_data(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        if (!is_numeric($userid)) {
            return;
        }
        $pluginname = get_string('pluginname', 'paygw_ifthenpay');
        foreach ($contextlist->get_contexts() as $context) {
            $accounts = [];
            foreach (self::recipient_accounts((int) $context->id, (int) $userid) as $account) {
                $accounts[] = (object) [
                    'description' => get_string('privacy:export:notifications', 'paygw_ifthenpay', $account->name),
                    'accountid' => $account->accountid,
                    'accountname' => $account->name,
                ];
            }
            if ($accounts) {
                writer::with_context($context)->export_data([$pluginname, 'notifications'], (object) ['accounts' => $accounts]);
            }
        }
    }

    /**
     * The ifthenpay gateway configurations that list notification recipients.
     *
     * The configuration is JSON, so the LIKE only narrows the rows down; the recipient list of
     * each row is then decoded and checked in PHP.
     *
     * @param int|null $contextid Only accounts in this context; null for any context.
     * @param int|null $userid Only configurations listing this user; null for any listed user.
     * @return array<int, object{id: int, accountid: int, name: string, contextid: int, config: array<string, mixed>,
     *     userids: int[]}> Keyed by payment_gateways id.
     */
    private static function recipient_accounts(?int $contextid, ?int $userid): array {
        global $DB;

        /** @var \moodle_database $db */
        $db = $DB;
        $where = 'pg.gateway = :gateway AND ' . $db->sql_like('pg.config', ':notifyusers');
        $params = ['gateway' => 'ifthenpay', 'notifyusers' => '%notify_users%'];
        if ($contextid !== null) {
            $where .= ' AND pa.contextid = :contextid';
            $params['contextid'] = $contextid;
        }
        $sql = "SELECT pg.id, pg.accountid, pg.config, pa.name, pa.contextid
                  FROM {payment_gateways} pg
                  JOIN {payment_accounts} pa ON pa.id = pg.accountid
                 WHERE $where";
        /** @var array<int, stdClass> $rows */
        $rows = $db->get_records_sql($sql, $params);

        $accounts = [];
        foreach ($rows as $row) {
            /** @var array<string, mixed>|null $config */
            $config = is_string($row->config) ? json_decode($row->config, true) : null;
            if (!is_array($config)) {
                continue;
            }
            $userids = notification_recipients::chosen_userids($config);
            if (!$userids || ($userid !== null && !in_array($userid, $userids, true))) {
                continue;
            }
            $id = is_numeric($row->id) ? (int) $row->id : 0;
            $accounts[$id] = (object) [
                'id' => $id,
                'accountid' => is_numeric($row->accountid) ? (int) $row->accountid : 0,
                'name' => is_scalar($row->name) ? (string) $row->name : '',
                'contextid' => is_numeric($row->contextid) ? (int) $row->contextid : 0,
                'config' => $config,
                'userids' => $userids,
            ];
        }
        return $accounts;
    }

    /**
     * Removes users from the recipient lists of the payment accounts in a context.
     *
     * Every other key of the gateway configuration is kept as it is.
     *
     * @param int $contextid Context the payment accounts belong to.
     * @param int[]|null $userids Users to remove; null to empty the lists.
     * @return void
     */
    private static function remove_recipients(int $contextid, ?array $userids): void {
        global $DB;

        /** @var \moodle_database $db */
        $db = $DB;
        foreach (self::recipient_accounts($contextid, null) as $account) {
            $keep = $userids === null ? [] : array_values(array_diff($account->userids, $userids));
            if ($keep === $account->userids) {
                continue;
            }
            $config = $account->config;
            $config['notify_users'] = array_map('strval', $keep);
            $json = json_encode($config);
            if ($json === false) {
                continue;
            }
            $db->update_record('payment_gateways', (object) [
                'id' => $account->id,
                'config' => $json,
                'timemodified' => time(),
            ]);
        }
    }
}
