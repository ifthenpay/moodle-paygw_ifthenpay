<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin setting for the Ifthenpay Backoffice Key.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\adminsetting;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/**
 * Backoffice Key setting, validated against ifthenpay before it is stored.
 */
class backofficekey extends \admin_setting_configpasswordunmask
{
    /** @var int Remote validation timeout (seconds). */
    protected $apitimeout = 5;

    /**
     * Validate the key before it is saved.
     *
     * An empty value is allowed, so the setting can be cleared. A non-empty one must match
     * ####-####-####-#### and be recognised by ifthenpay. Only an outright rejection blocks the
     * save; if the API is unreachable the key is stored anyway, so an outage cannot lock an admin
     * out of the settings page.
     *
     * @param mixed $data Raw value from the settings form.
     * @return bool|string True if valid, or an error message to show.
     */
    public function validate($data) {
        $data = is_string($data) ? trim($data) : $data;

        if ($data === '' || $data === null) {
            return true;
        }

        if (!is_string($data) || !preg_match('/^\d{4}(?:-\d{4}){3}$/', $data)) {
            return get_string('error_invalidformat', 'paygw_ifthenpay');
        }

        /*
         * This is the only place a Backoffice Key is verified, and it runs on every save: a key can
         * be revoked after it was stored, and CLI writes skip this method entirely, so "already in
         * the database" proves nothing.
         *
         * verify_backoffice_key() is the right check. get_gateway_keys() is not, because it returns
         * an empty list both for a new customer with no Moodle Gateway Keys and for a key belonging
         * to nobody. Having no Gateway Keys yet is a normal state — the onboarding steps tell
         * admins to enter this key before requesting one — so the settings page reports it
         * separately rather than blocking the save here.
         */
        try {
            if (!\paygw_ifthenpay_key_is_recognised($data, $this->apitimeout)) {
                return get_string('error_invalid_backoffice_key', 'paygw_ifthenpay');
            }
        } catch (\moodle_exception $e) {
            if ($e->errorcode === 'api:error_unauthorized') {
                return get_string('error_invalid_backoffice_key', 'paygw_ifthenpay');
            }
            // Transport and JSON errors say nothing about the key, so they do not block the save.
        } catch (\Throwable $e) {
            debugging('[ifthenpay] Backoffice Key validation error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        return true;
    }
}
