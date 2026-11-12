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

namespace paygw_ifthenpay\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\database_table;
use core_privacy\local\metadata\types\subsystem_link;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the privacy provider: transactions and staff-notification recipient lists.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \context System context. */
    private \context $sysctx;

    /** @var \context Course category context. */
    private \context $catctx;

    /** @var int Payment account in the system context. */
    private int $sysaccount;

    /** @var int Payment account in the category context. */
    private int $cataccount;

    /** @var int Recipient on both accounts, payer of one transaction. */
    private int $u1;

    /** @var int Recipient on the system account only. */
    private int $u2;

    /** @var int Recipient on the category account only. */
    private int $u3;

    /** @var int Payer of one transaction, recipient of nothing. */
    private int $payer;

    /**
     * Two payment accounts (system and category) with recipient lists, and two transactions.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $gen = $this->getDataGenerator();
        $this->u1 = self::id($gen->create_user());
        $this->u2 = self::id($gen->create_user());
        $this->u3 = self::id($gen->create_user());
        $this->payer = self::id($gen->create_user());

        $this->sysctx = \context_system::instance();
        $category = $gen->create_category();
        $catctx = \context_coursecat::instance(self::id($category));
        $this->assertInstanceOf(\context::class, $catctx);
        $this->catctx = $catctx;

        $this->sysaccount = $this->create_account((int) $this->sysctx->id, [(string) $this->u1, (string) $this->u2]);
        $this->cataccount = $this->create_account((int) $this->catctx->id, [$this->u1, $this->u3]);

        $this->create_tx($this->u1, $this->sysaccount);
        $this->create_tx($this->payer, $this->cataccount);
    }

    /**
     * A record's id as an int.
     *
     * @param mixed $record Record.
     * @return int
     */
    private static function id(mixed $record): int {
        return is_object($record) && isset($record->id) && is_numeric($record->id) ? (int) $record->id : 0;
    }

    /**
     * The database.
     *
     * @return \moodle_database
     */
    private static function db(): \moodle_database {
        global $DB;
        /** @var \moodle_database $db */
        $db = $DB;
        return $db;
    }

    /**
     * Creates a payment account with an ifthenpay gateway whose config lists recipients.
     *
     * @param int $contextid Context of the account.
     * @param array<int, int|string> $userids Chosen recipients.
     * @return int Account id.
     */
    private function create_account(int $contextid, array $userids): int {
        /** @var \core_payment_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['gateways' => 'ifthenpay', 'contextid' => $contextid]);
        $id = $account->get('id');
        $accountid = is_numeric($id) ? (int) $id : 0;
        $config = json_encode([
            'gatewaykey' => 'ABCD-123456',
            'notify_completed' => '1',
            'notify_problems' => '0',
            'notify_users' => $userids,
        ]);
        self::db()->set_field('payment_gateways', 'config', $config, ['accountid' => $accountid, 'gateway' => 'ifthenpay']);
        return $accountid;
    }

    /**
     * Inserts a transaction.
     *
     * @param int $userid Payer.
     * @param int $accountid Payment account.
     * @return int Transaction id.
     */
    private function create_tx(int $userid, int $accountid): int {
        return (int) self::db()->insert_record('paygw_ifthenpay_tx', (object) [
            'timecreated' => time(),
            'timemodified' => time(),
            'token' => random_string(32),
            'userid' => $userid,
            'component' => 'enrol_fee',
            'paymentarea' => 'fee',
            'itemid' => 1,
            'accountid' => $accountid,
            'amount' => 10,
            'currency' => 'EUR',
            'gateway_key' => 'ABCD-123456',
            'state' => 'PENDING',
        ]);
    }

    /**
     * The decoded gateway config of an account.
     *
     * @param int $accountid Account id.
     * @return array<string, mixed>
     */
    private static function config(int $accountid): array {
        $json = self::db()->get_field('payment_gateways', 'config', ['accountid' => $accountid, 'gateway' => 'ifthenpay']);
        /** @var array<string, mixed> $config */
        $config = is_string($json) ? json_decode($json, true) : [];
        return $config;
    }

    /**
     * The recipient ids of an account, sorted, as ints.
     *
     * @param int $accountid Account id.
     * @return int[]
     */
    private static function recipients(int $accountid): array {
        $raw = self::config($accountid)['notify_users'] ?? [];
        $ids = [];
        foreach (is_array($raw) ? $raw : [] as $id) {
            $ids[] = is_numeric($id) ? (int) $id : 0;
        }
        sort($ids);
        return $ids;
    }

    /**
     * Context ids of a context list, sorted.
     *
     * @param int $userid User.
     * @return int[]
     */
    private static function contextids(int $userid): array {
        $ids = array_map('intval', provider::get_contexts_for_userid($userid)->get_contextids());
        sort($ids);
        return $ids;
    }

    /**
     * User ids of a context, sorted.
     *
     * @param \context $context Context.
     * @return int[]
     */
    private static function userids(\context $context): array {
        $userlist = new userlist($context, 'paygw_ifthenpay');
        provider::get_users_in_context($userlist);
        $ids = array_map('intval', $userlist->get_userids());
        sort($ids);
        return $ids;
    }

    /**
     * Number of transactions of a user.
     *
     * @param int $userid User.
     * @return int
     */
    private static function txcount(int $userid): int {
        return self::db()->count_records('paygw_ifthenpay_tx', ['userid' => $userid]);
    }

    /**
     * An approved context list for a user.
     *
     * @param \stdClass $user User.
     * @param int ...$contextids Approved contexts.
     * @return approved_contextlist
     */
    private static function approved(\stdClass $user, int ...$contextids): approved_contextlist {
        // Built through reflection because core documents the parameter as \int[] (a class named
        // "int"), which no int array satisfies under static analysis.
        return (new \ReflectionClass(approved_contextlist::class))
            ->newInstanceArgs([$user, 'paygw_ifthenpay', array_values($contextids)]);
    }

    /**
     * An approved user list for a context.
     *
     * @param \context $context Context.
     * @param int ...$userids Approved users.
     * @return approved_userlist
     */
    private static function approved_users(\context $context, int ...$userids): approved_userlist {
        // Reflection for the same \int[] reason as approved().
        return (new \ReflectionClass(approved_userlist::class))
            ->newInstanceArgs([$context, 'paygw_ifthenpay', array_values($userids)]);
    }

    /**
     * The test content writer of a context.
     *
     * @param \context $context Context.
     * @return \core_privacy\tests\request\content_writer
     */
    private static function writer(\context $context): \core_privacy\tests\request\content_writer {
        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = writer::with_context($context);
        return $writer;
    }

    /**
     * The metadata describes the transactions, the recipient list and the messaging link.
     *
     * @return void
     */
    public function test_get_metadata(): void {
        $items = provider::get_metadata(new collection('paygw_ifthenpay'))->get_collection();
        $byname = [];
        foreach ($items as $item) {
            $byname[$item->get_name()] = $item;
        }
        $this->assertInstanceOf(database_table::class, $byname['paygw_ifthenpay_tx']);
        $this->assertInstanceOf(database_table::class, $byname['payment_gateways']);
        $this->assertSame(
            ['config' => 'privacy:metadata:payment_gateways:config'],
            $byname['payment_gateways']->get_privacy_fields()
        );
        $this->assertInstanceOf(subsystem_link::class, $byname['core_message']);
        foreach ($items as $item) {
            $summary = $item->get_summary();
            $this->assertIsString($summary);
            $this->assertTrue(get_string_manager()->string_exists($summary, 'paygw_ifthenpay'), $summary);
            $fields = $item->get_privacy_fields();
            $this->assertIsArray($fields);
            foreach ($fields as $identifier) {
                $this->assertIsString($identifier);
                $this->assertTrue(get_string_manager()->string_exists($identifier, 'paygw_ifthenpay'), $identifier);
            }
        }
    }

    /**
     * Contexts: system for transactions and system recipients, the category for its recipients.
     *
     * @return void
     */
    public function test_get_contexts_for_userid(): void {
        $sys = (int) $this->sysctx->id;
        $cat = (int) $this->catctx->id;
        $both = [$sys, $cat];
        sort($both);
        $this->assertSame($both, self::contextids($this->u1));
        $this->assertSame([$sys], self::contextids($this->u2));
        $this->assertSame([$cat], self::contextids($this->u3));
        $this->assertSame([$sys], self::contextids($this->payer));

        $nobody = self::id($this->getDataGenerator()->create_user());
        $this->assertSame([], self::contextids($nobody));
    }

    /**
     * Users: payers and system recipients in the system context, category recipients in it.
     *
     * @return void
     */
    public function test_get_users_in_context(): void {
        $expected = [$this->u1, $this->u2, $this->payer];
        sort($expected);
        $this->assertSame($expected, self::userids($this->sysctx));

        $expected = [$this->u1, $this->u3];
        sort($expected);
        $this->assertSame($expected, self::userids($this->catctx));

        $course = $this->getDataGenerator()->create_course();
        $coursectx = \context_course::instance(self::id($course));
        $this->assertInstanceOf(\context::class, $coursectx);
        $this->assertSame([], self::userids($coursectx));
    }

    /**
     * Export: one recipient entry per account in its own context, and the existing tx export.
     *
     * @return void
     */
    public function test_export_user_data(): void {
        $user = \core_user::get_user($this->u1);
        $this->assertInstanceOf(\stdClass::class, $user);
        provider::export_user_data(self::approved($user, (int) $this->sysctx->id, (int) $this->catctx->id));
        $subcontext = [get_string('pluginname', 'paygw_ifthenpay'), 'notifications'];

        $sysname = self::db()->get_field('payment_accounts', 'name', ['id' => $this->sysaccount]);
        $data = self::writer($this->sysctx)->get_data($subcontext);
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertIsArray($data->accounts);
        $this->assertCount(1, $data->accounts);
        $entry = $data->accounts[0];
        $this->assertInstanceOf(\stdClass::class, $entry);
        $this->assertSame($this->sysaccount, $entry->accountid);
        $this->assertIsString($sysname);
        $this->assertSame($sysname, $entry->accountname);
        $this->assertSame(
            get_string('privacy:export:notifications', 'paygw_ifthenpay', $sysname),
            $entry->description
        );

        $data = self::writer($this->catctx)->get_data($subcontext);
        $this->assertInstanceOf(\stdClass::class, $data);
        $this->assertIsArray($data->accounts);
        $this->assertCount(1, $data->accounts);
        $entry = $data->accounts[0];
        $this->assertInstanceOf(\stdClass::class, $entry);
        $this->assertSame($this->cataccount, $entry->accountid);

        // The transaction export is unchanged: one record under ['ifthenpay', <id>], without the userid.
        $txid = self::db()->get_field('paygw_ifthenpay_tx', 'id', ['userid' => $this->u1]);
        $tx = self::writer($this->sysctx)->get_data(['ifthenpay', is_numeric($txid) ? (string) $txid : '']);
        $this->assertInstanceOf(\stdClass::class, $tx);
        $this->assertSame('EUR', $tx->currency);
        $this->assertSame('PENDING', $tx->state);
        $this->assertObjectNotHasProperty('userid', $tx);
        $this->assertObjectNotHasProperty('gateway_key', $tx);
    }

    /**
     * Export of an approved category context only: nothing in the system context.
     *
     * @return void
     */
    public function test_export_user_data_category_only(): void {
        $user = \core_user::get_user($this->u1);
        $this->assertInstanceOf(\stdClass::class, $user);
        provider::export_user_data(self::approved($user, (int) $this->catctx->id));

        $this->assertFalse(self::writer($this->sysctx)->has_any_data());
        $this->assertTrue(self::writer($this->catctx)->has_any_data());
    }

    /**
     * Per-user delete: removed from the lists of approved contexts only; tx deleted for system.
     *
     * @return void
     */
    public function test_delete_data_for_user(): void {
        $user = \core_user::get_user($this->u1);
        $this->assertInstanceOf(\stdClass::class, $user);

        provider::delete_data_for_user(self::approved($user, (int) $this->catctx->id));
        $this->assertSame([$this->u3], self::recipients($this->cataccount));
        $this->assertSame([$this->u1, $this->u2], self::recipients($this->sysaccount));
        $this->assertSame(1, self::txcount($this->u1));

        provider::delete_data_for_user(self::approved($user, (int) $this->sysctx->id));
        $this->assertSame([$this->u2], self::recipients($this->sysaccount));
        $this->assertSame(0, self::txcount($this->u1));
        $this->assertSame(1, self::txcount($this->payer));

        // Every other configuration key is preserved.
        $config = self::config($this->sysaccount);
        $this->assertSame('ABCD-123456', $config['gatewaykey']);
        $this->assertSame('1', $config['notify_completed']);
        $this->assertSame('0', $config['notify_problems']);
    }

    /**
     * Per-userlist delete in the category and system contexts.
     *
     * @return void
     */
    public function test_delete_data_for_users(): void {
        provider::delete_data_for_users(self::approved_users($this->catctx, $this->u1, $this->u3));
        $this->assertSame([], self::recipients($this->cataccount));
        $this->assertSame([$this->u1, $this->u2], self::recipients($this->sysaccount));
        $this->assertSame(1, self::txcount($this->payer));

        provider::delete_data_for_users(self::approved_users($this->sysctx, $this->u2, $this->payer));
        $this->assertSame([$this->u1], self::recipients($this->sysaccount));
        $this->assertSame(0, self::txcount($this->payer));
        $this->assertSame(1, self::txcount($this->u1));
        $this->assertSame('ABCD-123456', self::config($this->sysaccount)['gatewaykey']);
    }

    /**
     * Context delete of a category: its lists emptied, transactions untouched.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_category_context(): void {
        provider::delete_data_for_all_users_in_context($this->catctx);
        $this->assertSame([], self::recipients($this->cataccount));
        $this->assertSame('ABCD-123456', self::config($this->cataccount)['gatewaykey']);
        $this->assertSame([$this->u1, $this->u2], self::recipients($this->sysaccount));
        $this->assertSame(2, self::db()->count_records('paygw_ifthenpay_tx'));
    }

    /**
     * Context delete of the system: its lists emptied and every transaction deleted.
     *
     * @return void
     */
    public function test_delete_data_for_all_users_in_system_context(): void {
        provider::delete_data_for_all_users_in_context($this->sysctx);
        $this->assertSame([], self::recipients($this->sysaccount));
        $this->assertSame([$this->u1, $this->u3], self::recipients($this->cataccount));
        $this->assertSame(0, self::db()->count_records('paygw_ifthenpay_tx'));
    }
}
