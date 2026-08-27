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
 * Event raised when a payment cannot be completed.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\event;

/**
 * Raised when a webhook cannot be completed — most importantly when money has been captured but
 * the order was not delivered.
 *
 * This exists because `debugging()` is a no-op on production sites, and a customer paying without
 * receiving their order must leave a trace an administrator can actually find. Site logs are where
 * they look, and they can be reported on and alerted from.
 */
class payment_problem extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Human-readable event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event:payment_problem', 'paygw_ifthenpay');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description(): string {
        return "The ifthenpay payment with token '{$this->other['token']}' could not be completed: "
            . $this->other['problem'];
    }
}
