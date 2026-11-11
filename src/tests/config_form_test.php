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

namespace paygw_ifthenpay;

use core_payment\account_gateway as gateway_persistent;
use paygw_ifthenpay\local\config_form;
use core_payment\form\account_gateway;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the staff notification section of the account gateway form.
 *
 * The ifthenpay API is never called: the request caches the form reads through are primed
 * instead, which is what lets the normal (working Backoffice Key) path run offline.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(config_form::class)]
final class config_form_test extends \advanced_testcase {
    /** @var string[] The notification settings the form must render on both paths. */
    private const NOTIFY_FIELDS = ['notify_completed', 'notify_problems', 'notify_admins', 'notify_teachers', 'notify_users'];

    /**
     * The gateway must be enabled: Moodle hides the message providers of disabled plugins.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        \core\plugininfo\paygw::enable_plugin('ifthenpay', 1);
    }

    /**
     * Make the Backoffice Key look working, with one Gateway Key carrying MB WAY.
     *
     * @return void
     */
    private function prime_working_key(): void {
        set_config('backoffice_key', 'TESTKEY', 'paygw_ifthenpay');
        $data = \cache::make('paygw_ifthenpay', 'gatewaydata');
        $data->set('status:TESTKEY', 'ok');
        $data->set('TESTKEY', [
            'gatewaykeys' => ['GK1' => 'Gateway key 1'],
            'accounts' => ['GK1' => ['MBWAY' => 'ACC-1']],
        ]);
        \cache::make('paygw_ifthenpay', 'methods')->set('available', [
            'MBWAY' => ['position' => 1, 'image' => '', 'tooltip' => '', 'label' => 'MB WAY'],
        ]);
    }

    /**
     * An ifthenpay gateway on a new payment account, optionally with a saved configuration.
     *
     * @param array<string, mixed> $config Saved gateway configuration.
     * @param bool $enabled Whether the gateway is switched on.
     * @return gateway_persistent
     */
    private function gateway(array $config = [], bool $enabled = true): gateway_persistent {
        /** @var \core_payment_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $account = $generator->create_payment_account(['gateways' => 'ifthenpay']);
        $gateway = $account->get_gateways()['ifthenpay'];
        $gateway->set('config', json_encode($config));
        $gateway->set('enabled', (int) $enabled);
        $gateway->save();
        return $gateway;
    }

    /**
     * Build the form core shows for this gateway.
     *
     * @param gateway_persistent $gateway Gateway.
     * @return account_gateway
     */
    private function form(gateway_persistent $gateway): account_gateway {
        return new account_gateway(null, ['persistent' => $gateway]);
    }

    /**
     * A form element's current value.
     *
     * @param account_gateway $form Form.
     * @param string $name Element name.
     * @return mixed
     */
    private static function value(account_gateway $form, string $name): mixed {
        $element = $form->get_mform()->getElement($name);
        return $element instanceof \HTML_QuickForm_element ? $element->getValue() : null;
    }

    /**
     * A form element's type.
     *
     * @param account_gateway $form Form.
     * @param string $name Element name.
     * @return string
     */
    private function type(account_gateway $form, string $name): string {
        $element = $form->get_mform()->getElement($name);
        $this->assertInstanceOf(\HTML_QuickForm_element::class, $element);
        return (string) $element->getType();
    }

    /**
     * A record's id as an int.
     *
     * @param \stdClass $record Record.
     * @return int
     */
    private static function id(\stdClass $record): int {
        return is_numeric($record->id ?? null) ? (int) $record->id : 0;
    }

    /**
     * Run the gateway's validation on a submission, the way core's form does.
     *
     * @param array<string, mixed> $data Submitted data.
     * @return array<string, string> Errors by element name.
     */
    private function validate(array $data): array {
        $errors = [];
        gateway::validate_gateway_form($this->form($this->gateway()), (object) $data, [], $errors);
        return $errors;
    }

    /**
     * A new user holding the editing teacher role in a new course, so able to receive.
     *
     * @return \stdClass
     */
    private function teacher(): \stdClass {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user(['firstname' => 'Tina', 'lastname' => 'Teacher']);
        $this->getDataGenerator()->enrol_user(self::id($user), self::id($course), 'editingteacher');
        return $user;
    }

    /**
     * Normal path: every option is off on an account that has never saved them.
     *
     * @return void
     */
    public function test_normal_path_defaults_are_off(): void {
        $this->setAdminUser();
        $this->prime_working_key();
        $form = $this->form($this->gateway());
        $mform = $form->get_mform();

        $this->assertTrue($mform->elementExists('ifthenpay_gatewaykey'), 'normal path rendered');
        foreach (self::NOTIFY_FIELDS as $name) {
            $this->assertTrue($mform->elementExists($name), $name);
        }
        $this->assertSame('autocomplete', $this->type($form, 'notify_users'));
        foreach (['notify_completed', 'notify_problems', 'notify_admins', 'notify_teachers'] as $name) {
            $this->assertEquals(0, self::value($form, $name), $name);
        }
        $this->assertSame([], self::value($form, 'notify_users'));
    }

    /**
     * Normal path: saved choices come back, shown by name, a since-deleted user included.
     *
     * @return void
     */
    public function test_normal_path_saved_values_come_back(): void {
        $this->setAdminUser();
        $this->prime_working_key();
        $teacher = $this->teacher();
        $gone = $this->getDataGenerator()->create_user(['firstname' => 'Gus', 'lastname' => 'Gone']);
        delete_user($gone);

        $form = $this->form($this->gateway([
            'notify_completed' => '1',
            'notify_problems' => '0',
            'notify_admins' => '0',
            'notify_teachers' => '1',
            'notify_users' => [(string) self::id($teacher), (string) self::id($gone)],
        ]));

        $this->assertEquals(1, self::value($form, 'notify_completed'));
        $this->assertEquals(0, self::value($form, 'notify_problems'));
        $this->assertEquals(0, self::value($form, 'notify_admins'));
        $this->assertEquals(1, self::value($form, 'notify_teachers'));
        $this->assertEquals([self::id($teacher), self::id($gone)], self::value($form, 'notify_users'));

        // The selector shows the saved users by name, so a deleted one can be spotted and removed.
        $html = $form->render();
        $this->assertStringContainsString('Tina Teacher', $html);
        $this->assertStringContainsString('Gus Gone', $html);
    }

    /**
     * Unavailable path: the section is still there, and its keys are not duplicated as hidden fields.
     *
     * @return void
     */
    public function test_unavailable_path_renders_section(): void {
        $this->setAdminUser();
        $this->assertSame('unconfigured', \paygw_ifthenpay_backoffice_key_status());
        $form = $this->form($this->gateway(['notify_completed' => '1', 'ifthenpay_description' => 'Kept']));
        $mform = $form->get_mform();

        $this->assertTrue($mform->elementExists('ifthenpay_unavailable'), 'unavailable path rendered');
        foreach (self::NOTIFY_FIELDS as $name) {
            $this->assertTrue($mform->elementExists($name), $name);
        }
        $this->assertSame('advcheckbox', $this->type($form, 'notify_completed'));
        $this->assertEquals(1, self::value($form, 'notify_completed'));
        $this->assertSame('hidden', $this->type($form, 'ifthenpay_description'));
    }

    /**
     * Unavailable path, gateway switched off: a save keeps the chosen users and the old config.
     *
     * @return void
     */
    public function test_unavailable_path_save_keeps_choices(): void {
        $this->setAdminUser();
        $teacher = $this->teacher();
        $gateway = $this->gateway(['ifthenpay_description' => 'Kept'], false);

        account_gateway::mock_submit([
            'id' => $gateway->get('id'),
            'accountid' => $gateway->get('accountid'),
            'gateway' => 'ifthenpay',
            'enabled' => 0,
            'ifthenpay_description' => 'Kept',
            'notify_completed' => 1,
            'notify_problems' => 0,
            'notify_admins' => 0,
            'notify_teachers' => 0,
            'notify_users' => [(string) self::id($teacher)],
        ]);
        $data = $this->form($gateway)->get_data();

        $this->assertIsObject($data);
        $config = json_decode(is_string($data->config ?? null) ? $data->config : '', true);
        $this->assertIsArray($config);
        $this->assertEquals([self::id($teacher)], $config['notify_users']);
        $this->assertEquals(1, $config['notify_completed']);
        $this->assertSame('Kept', $config['ifthenpay_description']);
    }

    /**
     * validate() refuses a chosen user who cannot receive, by name, on both paths; accepts others.
     *
     * @return void
     */
    public function test_validate_refuses_user_without_capability(): void {
        $this->setAdminUser();
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Sam', 'lastname' => 'Student']);
        $teacher = $this->teacher();
        $admin = get_admin();
        $adminid = $admin ? (string) self::id($admin) : '';

        foreach (['unavailable', 'normal'] as $path) {
            if ($path === 'normal') {
                $this->prime_working_key();
            }

            $errors = $this->validate(['notify_users' => [(string) self::id($student), $adminid]]);
            $this->assertArrayHasKey('notify_users', $errors, $path);
            $this->assertStringContainsString('Sam Student', $errors['notify_users'], $path);
            $this->assertStringContainsString('paygw/ifthenpay:receivenotifications', $errors['notify_users'], $path);
            $this->assertStringNotContainsString('Admin', $errors['notify_users'], $path);

            $errors = $this->validate(['notify_users' => [$adminid, (string) self::id($teacher)]]);
            $this->assertArrayNotHasKey('notify_users', $errors, $path);

            // An empty selector submits nothing usable; that is fine.
            $errors = $this->validate(['notify_users' => ['_qf__force_multiselect_submission']]);
            $this->assertArrayNotHasKey('notify_users', $errors, $path);
        }
    }

    /**
     * An editor who cannot search users sees the chosen ones read-only, and a save keeps them.
     *
     * @return void
     */
    public function test_readonly_users_without_viewalldetails(): void {
        $teacher = $this->teacher();
        $student = $this->getDataGenerator()->create_user(['firstname' => 'Sam', 'lastname' => 'Student']);
        // The dropped user first, so the kept one changes position.
        $gateway = $this->gateway(['notify_users' => [(string) self::id($student), (string) self::id($teacher)]], false);

        // A user whose only extra power is to manage payment accounts.
        $editor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/payment:manageaccounts', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, self::id($editor), \context_system::instance()->id);
        $this->setUser($editor);
        $this->assertFalse(has_capability('moodle/user:viewalldetails', \context_system::instance()));

        $form = $this->form($gateway);
        $mform = $form->get_mform();
        $this->assertSame('group', $this->type($form, 'notify_users'));
        $html = $form->render();
        $this->assertStringContainsString('Tina Teacher', $html);
        $this->assertStringContainsString('Sam Student', $html);
        $this->assertStringContainsString('removed when saved', $html);

        // What the browser would post back: the hidden value only carries the teacher.
        $this->assertMatchesRegularExpression(
            '~name="notify_users\[0\]"[^>]*value="' . self::id($teacher) . '"~',
            $html
        );
        $this->assertStringNotContainsString('name="notify_users[1]"', $html);

        account_gateway::mock_submit([
            'id' => $gateway->get('id'),
            'accountid' => $gateway->get('accountid'),
            'gateway' => 'ifthenpay',
            'enabled' => 0,
            // A tampered post: the read-only list is a constant, so this is ignored.
            'notify_users' => [(string) self::id($student)],
        ]);
        $data = $this->form($gateway)->get_data();
        $this->assertIsObject($data);
        $config = json_decode(is_string($data->config ?? null) ? $data->config : '', true);
        $this->assertIsArray($config);
        $this->assertEquals([self::id($teacher)], $config['notify_users']);
    }

    /**
     * With "payment completed" disabled site-wide, a chosen user who holds the capability is still
     * valid: validate() accepts them on both paths, and the read-only path keeps them on save.
     *
     * @return void
     */
    public function test_site_wide_disable_keeps_chosen_users(): void {
        set_config('paygw_ifthenpay_paymentcompleted_disable', 1, 'message');
        $teacher = $this->teacher();
        $teacherid = (string) self::id($teacher);

        $gateway = $this->gateway(['notify_users' => [$teacherid]], false);
        $editor = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/payment:manageaccounts', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, self::id($editor), \context_system::instance()->id);
        $this->setUser($editor);

        $html = $this->form($gateway)->render();
        $this->assertStringContainsString('Tina Teacher', $html);
        $this->assertStringNotContainsString('removed when saved', $html);

        account_gateway::mock_submit([
            'id' => $gateway->get('id'),
            'accountid' => $gateway->get('accountid'),
            'gateway' => 'ifthenpay',
            'enabled' => 0,
        ]);
        $data = $this->form($gateway)->get_data();
        $this->assertIsObject($data);
        $config = json_decode(is_string($data->config ?? null) ? $data->config : '', true);
        $this->assertIsArray($config);
        $this->assertEquals([self::id($teacher)], $config['notify_users']);

        // validate(), on both paths (the read-only save above ran on the unavailable path). The
        // mocked submission is cleared first, or the next form would take it for its own.
        $_POST = [];
        $this->setAdminUser();
        foreach (['unavailable', 'normal'] as $path) {
            if ($path === 'normal') {
                $this->prime_working_key();
            }
            $errors = $this->validate(['notify_users' => [$teacherid]]);
            $this->assertArrayNotHasKey('notify_users', $errors, $path);
        }
    }
}
