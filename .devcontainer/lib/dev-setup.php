<?php
// This file is part of the ifthenpay Moodle plugin development environment.
//
// Executed by the app container's entrypoint on every start.

/**
 * Development helper: enable the ifthenpay payment gateway so the dev site
 * is usable immediately after install. Everything else (disabling it,
 * setting the Backoffice Key) is done through Site administration in Moodle
 * itself, like any other plugin.
 *
 * @package    paygw_ifthenpay
 * @copyright  2026 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

$dirroot = getenv('MOODLE_DIRROOT') ?: '/var/www/html';
require($dirroot . '/config.php');

if (get_config('paygw_ifthenpay', 'version') === false) {
    echo 'paygw_ifthenpay is not installed yet — skipping gateway setup.' . PHP_EOL;
    exit(0);
}

\core\plugininfo\paygw::enable_plugin('ifthenpay', 1);
echo 'Gateway ifthenpay enabled.' . PHP_EOL;
