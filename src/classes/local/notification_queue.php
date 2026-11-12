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

use paygw_ifthenpay\task\send_notification;

/**
 * Puts staff notification tasks on Moodle's ad-hoc queue.
 *
 * Resolved through Moodle's DI container (\core\di) rather than called statically, so the
 * notification step's failure handling can be tested by swapping in a queue that fails. Breaking
 * core's own queue for that relied on core internals that differ between supported releases.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notification_queue {
    /**
     * Queue one notification task.
     *
     * @param send_notification $task Task with its custom data set.
     * @return void
     * @throws \RuntimeException When Moodle does not queue it.
     */
    public function add(send_notification $task): void {
        if (!\core\task\manager::queue_adhoc_task($task)) {
            throw new \RuntimeException('the notification task was not queued');
        }
    }
}
