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

defined('MOODLE_INTERNAL') || die();

use PHPUnit\Framework\Attributes\CoversFunction;

require_once(__DIR__ . '/../lib.php');

/**
 * Unit tests for the shared functions in lib.php.
 *
 * @package    paygw_ifthenpay
 * @category   test
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('paygw_ifthenpay_amount_to_cents')]
final class lib_test extends \advanced_testcase {
    /**
     * Valid amounts, with either decimal separator, convert to integer cents.
     *
     * @return void
     */
    public function test_amount_to_cents_valid(): void {
        $this->assertSame(1000, paygw_ifthenpay_amount_to_cents('10'));
        $this->assertSame(1050, paygw_ifthenpay_amount_to_cents('10.5'));
        $this->assertSame(1050, paygw_ifthenpay_amount_to_cents('10,50'));
    }

    /**
     * Malformed, negative or over-precise amounts are rejected with null.
     *
     * @return void
     */
    public function test_amount_to_cents_invalid(): void {
        $this->assertNull(paygw_ifthenpay_amount_to_cents('abc'));
        $this->assertNull(paygw_ifthenpay_amount_to_cents('-1'));
        $this->assertNull(paygw_ifthenpay_amount_to_cents('1.234'));
    }
}
