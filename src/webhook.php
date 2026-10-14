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
 * Webhook for receiving events from ifthenpay.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('NO_MOODLE_COOKIES', true);
// The status code is what ifthenpay reads, so debugging output goes to the error log rather than into the
// response, where on a developer site it would turn an answer into an error page.
define('NO_DEBUG_DISPLAY', true);

require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');

@header('Content-Type: text/plain; charset=utf-8');

// Read with optional_param and checked below: required_param throws on a missing parameter, and
// Moodle would then answer with an HTML error page instead of the plain-text reply ifthenpay reads.
$token  = optional_param('reference', '', PARAM_ALPHANUMEXT);
$amount = optional_param('amount', '', PARAM_RAW_TRIMMED);
$apk    = optional_param('apk', '', PARAM_RAW_TRIMMED);

if ($token === '' || $amount === '' || $apk === '') {
    echo 'INVALID';
    exit;
}

$result = paygw_ifthenpay_process_webhook($token, $amount, $apk);

// Idempotent: ifthenpay retries, and a retry of a settled payment must still answer OK.
// Transient failures (lock busy, delivery refused, database error) answer 503 so they are retried;
// rejections answer 200 INVALID, so a forged or malformed call is not retried indefinitely.
if ($result === PAYGW_IFTHENPAY_WEBHOOK_RETRY) {
    http_response_code(503);
    echo 'RETRY';
    exit;
}

echo $result === PAYGW_IFTHENPAY_WEBHOOK_OK ? 'OK' : 'INVALID';
