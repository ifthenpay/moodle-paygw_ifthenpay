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
    http_response_code(400);
    echo 'INVALID: missing reference, amount or apk';
    exit;
}

$result = paygw_ifthenpay_process_webhook($token, $amount, $apk);

/*
 * Idempotent: ifthenpay retries, and a retry of a settled payment must still answer OK.
 *
 * Every other outcome answers a distinct non-2xx code. ifthenpay's callback log records only the
 * status, so a single 200 INVALID made a misrouted callback indistinguishable from any other
 * rejection; and ifthenpay stops on any 2xx, so a rejection answered 200 was never retried even
 * when the cause (an unknown reference, say) is fixed within the retry window. The cost is that a
 * genuine callback that stays rejected is retried, and reported in the site log, on each of
 * ifthenpay's bounded attempts. A forged call is not retried at all: ifthenpay only retries its own.
 */
$responses = [
    PAYGW_IFTHENPAY_WEBHOOK_OK       => [200, 'OK'],
    PAYGW_IFTHENPAY_WEBHOOK_UNKNOWN  => [404, 'INVALID: unknown reference'],
    PAYGW_IFTHENPAY_WEBHOOK_BADKEY   => [403, 'INVALID: anti-phishing key mismatch'],
    PAYGW_IFTHENPAY_WEBHOOK_MISMATCH => [409, 'INVALID: amount mismatch'],
];
// Transient failures (lock busy, delivery refused, database error) answer 503 so they are retried.
[$status, $body] = $responses[$result] ?? [503, 'RETRY'];

http_response_code($status);
echo $body;
