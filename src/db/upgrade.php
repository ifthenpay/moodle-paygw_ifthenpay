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
 * Upgrade steps for the ifthenpay payment gateway.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Apply outstanding upgrade steps.
 *
 * @param int $oldversion Version currently installed.
 * @return bool
 */
function xmldb_paygw_ifthenpay_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    /*
     * v1.1.0 created the transaction table as "moodle-paygw_ifthenpay_tx"; v1.1.2 renamed it to
     * paygw_ifthenpay_tx in install.xml. install.xml only runs on a fresh install, and no upgrade
     * step was ever shipped, so a site that installed v1.1.0 still has the old table and never got
     * the new one. Every entry point then fails — checkout included, after a payment link has
     * already been created at ifthenpay — and the old table keeps personal data that the privacy
     * provider no longer knows to export or delete.
     *
     * Renaming preserves those rows, so in-flight payments still settle and the privacy provider
     * sees the historic data again. Guarded by table_exists so it is safe on every other path.
     */
    if ($oldversion < 2026082600) {
        $legacy = new xmldb_table('moodle-paygw_ifthenpay_tx');
        $current = new xmldb_table('paygw_ifthenpay_tx');

        if ($dbman->table_exists($legacy)) {
            if (!$dbman->table_exists($current)) {
                $dbman->rename_table($legacy, 'paygw_ifthenpay_tx');
            } else {
                /*
                 * Both tables present, so the rename cannot be done without choosing which rows to
                 * keep. Left in place rather than dropped — it may hold real payment records — but
                 * said loudly, because while it exists the privacy provider can neither export nor
                 * delete those rows, and uninstall will not remove it either.
                 */
                debugging(
                    'paygw_ifthenpay: the legacy table moodle-paygw_ifthenpay_tx still exists alongside '
                        . 'paygw_ifthenpay_tx. Its rows are invisible to the privacy provider and to '
                        . 'uninstall. Merge or drop it manually.',
                    DEBUG_DEVELOPER
                );
            }
        }

        upgrade_plugin_savepoint(true, 2026082600, 'paygw', 'ifthenpay');
    }

    return true;
}
