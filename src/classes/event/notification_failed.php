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
 * Event raised when a staff payment notification could not be queued or sent.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\event;

/**
 * Raised when a staff notification about a payment could not be queued or sent.
 *
 * Payment and enrolment are never affected by it; this is the trace an administrator can find in
 * the site logs. The recipient, when there is one, is the event's related user — never part of
 * `other` — so the logstore's privacy provider can export and delete it.
 *
 * `other`: txid (int), event ('completed' or 'problem'), problemtype (string or null), reason
 * (string, never a key or secret).
 */
class notification_failed extends \core\event\base {
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
        return get_string('event:notification_failed', 'paygw_ifthenpay');
    }

    /**
     * Description for the log report.
     *
     * @return string
     */
    public function get_description(): string {
        $other = is_array($this->other) ? $this->other : [];
        $txid = is_numeric($other['txid'] ?? null) ? (int) $other['txid'] : 0;
        $event = is_string($other['event'] ?? null) ? $other['event'] : '';
        $type = is_string($other['problemtype'] ?? null) ? ' (' . $other['problemtype'] . ')' : '';
        $reason = is_string($other['reason'] ?? null) ? $other['reason'] : '';
        $to = $this->relateduserid ? " to the user with id '{$this->relateduserid}'" : '';
        return "The ifthenpay '{$event}{$type}' notification for transaction id '{$txid}' could not be sent{$to}: "
            . $reason;
    }
}
