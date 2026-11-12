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

namespace paygw_ifthenpay\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the formatting of ifthenpay's method catalog.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(data_formatter::class)]
final class data_formatter_test extends \advanced_testcase {
    /**
     * Known methods get their brand spelling; an unknown one keeps the catalog's name; order and
     * visibility are respected.
     *
     * @return void
     */
    public function test_method_display_names(): void {
        $methods = data_formatter::format_available_payment_methods([
            ['Entity' => 'MBWAY', 'Method' => 'MBWAY', 'Position' => 2, 'IsVisible' => true],
            ['Entity' => 'MB', 'Method' => 'MULTIBANCO', 'Position' => 1, 'IsVisible' => true],
            ['Entity' => 'NEWPAY', 'Method' => 'NEW PAY', 'Position' => 3, 'IsVisible' => true],
            ['Entity' => 'HIDDEN', 'Method' => 'HIDDEN', 'Position' => 4, 'IsVisible' => false],
        ]);

        $this->assertSame(['MB', 'MBWAY', 'NEWPAY'], array_keys($methods));
        $this->assertSame('Multibanco', $methods['MB']['label']);
        $this->assertSame('MB WAY', $methods['MBWAY']['label']);
        $this->assertSame('NEW PAY', $methods['NEWPAY']['label']);
    }
}
