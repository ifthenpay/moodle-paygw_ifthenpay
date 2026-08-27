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
 * Ifthenpay API client for Moodle 4.3+.
 *
 * @package    paygw_ifthenpay
 * @copyright  2025 ifthenpay <geral@ifthenpay.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_ifthenpay\local;

use moodle_exception;

/**
 * Thin cURL wrapper around the ifthenpay HTTP APIs.
 *
 * Every method uses the key given at construction; none takes one as a parameter. Neither the
 * format of that key nor its validity is checked here — see __construct().
 */
final class api_client
{
    /** @var string Public API base URL. */
    private const BASE_API_PUBLIC = 'https://api.ifthenpay.com';
    /** @var string Endpoint to list available payment methods. */
    private const ENDPOINT_AVAILABLE_METHODS = '/gateway/methods/available';
    /** @var string Endpoint to create Pay-by-Link. */
    private const ENDPOINT_PAY_BY_LINK = '/gateway/pinpay';
    /** @var string Endpoint to get gateway details. */
    private const ENDPOINT_GATEWAY_GET = '/gateway/get';
    /** @var string Endpoint to activate callback URL. */
    private const ENDPOINT_CALLBACK_ACTIVATION = '/endpoint/callback/activation';

    // Entities/subentities (single URL). The only endpoint that distinguishes a client from a
    // non-client: it answers 403 for an unknown Backoffice Key, whereas /gateway/get answers 200
    // with an empty list both for a client who has no Moodle Gateway Keys yet and for a key that
    // belongs to nobody. Used solely to validate a key, never on the payment path.
    /** @var string Service URL for entities/subentities JSON. */
    private const ENTITIES_SUBENTIDADES_URL = 'https://ifthenpay.com/IfmbWS/ifmbws.asmx/getEntidadeSubentidadeJsonV2';

    /** @var string Backoffice Key. */
    protected string $backofficekey;

    /** @var int Request timeout (seconds). */
    protected int $timeout;

    /**
     * Constructor.
     *
     * Does not verify the key remotely. Every endpoint here already answers that question on its
     * own — an unusable key comes back as 401/403, surfaced as 'api:error_unauthorized' — so a
     * dedicated pre-flight check would be a second round trip that proves nothing extra, on every
     * construction including the checkout path. Whether a key is acceptable is settled once, when
     * it is saved, by the backoffice_key admin setting.
     *
     * @param string $backofficekey Backoffice Key (format already validated).
     * @param int $timeout Timeout in seconds (default 10).
     * @throws moodle_exception If no key is supplied.
     */
    public function __construct(string $backofficekey, int $timeout = 10) {
        $backofficekey = trim($backofficekey);
        if ($backofficekey === '') {
            throw new moodle_exception('api:nobackofficekey_error', 'paygw_ifthenpay');
        }
        $this->timeout = max(1, $timeout);
        $this->backofficekey = $backofficekey;
    }

    /**
     * Get globally available payment methods.
     *
     * Static: ENDPOINT_AVAILABLE_METHODS takes no boKey — it is ifthenpay's public catalog, not
     * account-specific data — so this needs neither an instance nor a validated key. Any failure
     * yields an empty array; the caller treats that as "nothing to show".
     *
     * @return array<array-key, mixed> Decoded response, or empty array on any failure.
     */
    public static function get_available_payment_methods(): array {
        $body = self::http('GET', self::BASE_API_PUBLIC . self::ENDPOINT_AVAILABLE_METHODS, null, 8);
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    /**
     * Check whether the Backoffice Key belongs to an ifthenpay client.
     *
     * Deliberately separate from the constructor: this is a question worth asking when a key is
     * being saved, not on every request. Note that get_gateway_keys() cannot answer it — an empty
     * result there means "no Moodle Gateway Keys", which is also what a stranger's key returns.
     *
     * @return bool True if the key is recognised.
     * @throws moodle_exception 'api:error_unauthorized' if the key is refused; transport/JSON errors otherwise.
     */
    public function verify_backoffice_key(): bool {
        $url = self::ENTITIES_SUBENTIDADES_URL . '?chavebackoffice=' . rawurlencode($this->backofficekey);
        $data = $this->get_json($url);
        return is_array($data) && $data !== [];
    }

    /**
     * Get Gateway Keys for the validated Backoffice Key (Moodle context).
     *
     * @return array<array-key, mixed> Decoded response or empty array on non-array payloads.
     * @throws moodle_exception On transport/JSON errors.
     */
    public function get_gateway_keys(): array {
        $url = self::BASE_API_PUBLIC . self::ENDPOINT_GATEWAY_GET
            . '?boKey=' . rawurlencode($this->backofficekey)
            . '&type=Moodle';
        $data = $this->get_json($url);
        return is_array($data) ? $data : [];
    }

    /**
     * Create a Pay-by-Link.
     *
     * @param string $gatewaykey Gateway Key.
     * @param array<string, mixed> $payload Request payload.
     * @return object Object with properties: pin_code, pinpay_url, redirect_url.
     * @throws moodle_exception On transport/JSON errors or invalid response shape.
     */
    public function create_pay_by_link(string $gatewaykey, array $payload): object {
        $url = self::BASE_API_PUBLIC . self::ENDPOINT_PAY_BY_LINK
            . '/' . rawurlencode($gatewaykey);

        $resp = $this->post_json($url, $payload);

        if (empty($resp['PinCode']) || empty($resp['PinpayUrl']) || empty($resp['RedirectUrl'])) {
            throw new moodle_exception('api:error_invalid_pbl_response', 'paygw_ifthenpay');
        }

        return (object)[
            'pin_code'     => $resp['PinCode'],
            'pinpay_url'   => $resp['PinpayUrl'],
            'redirect_url' => $resp['RedirectUrl'],
        ];
    }

    /**
     * Register this site's webhook URL against a gateway key.
     *
     * The placeholders in urlCb are substituted by ifthenpay when it calls back. The endpoint
     * answers with plain text, "OK" or "INVALID", rather than JSON.
     *
     * @param string $gatewaykey The Ifthenpay Gateway Key.
     * @return bool True on "OK", false otherwise.
     * @throws \moodle_exception On transport or JSON errors (via post_json()).
     */
    public function activate_callback_by_gateway_context(string $gatewaykey): bool {
        $url = self::BASE_API_PUBLIC . self::ENDPOINT_CALLBACK_ACTIVATION . '/?cms=moodle';

        $payload = [
            'apKey' => base64_encode($gatewaykey),
            'chave' => $gatewaykey,
            'urlCb' => (new \moodle_url('/payment/gateway/ifthenpay/webhook.php'))->out(false) .
                '?amount=[AMOUNT]&reference=[ORDER_ID]&apk=[ANTI_PHISHING_KEY]',
        ];

        $response = $this->post_json($url, $payload);

        return trim((string)$response) === 'OK';
    }

    /**
     * Perform a request and return the raw body.
     *
     * Static because the only per-instance input is the timeout, which lets the keyless catalog
     * call share this transport instead of rolling its own.
     *
     * @param string      $method  'GET' or 'POST'.
     * @param string      $url     URL.
     * @param string|null $rawbody JSON body for POST.
     * @param int         $timeout Timeout in seconds.
     * @return string Response body.
     * @throws moodle_exception On transport failure, rejected credentials, or HTTP >= 400.
     */
    protected static function http(string $method, string $url, ?string $rawbody, int $timeout): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['timeout' => $timeout]);
        $curl->setHeader($rawbody === null
            ? ['Accept: application/json']
            : ['Accept: application/json', 'Content-Type: application/json']);

        try {
            $body = $method === 'POST' ? $curl->post($url, $rawbody) : $curl->get($url);
        } catch (\Throwable $e) {
            throw new moodle_exception('api:error_http_request_failed', 'paygw_ifthenpay', '', $e->getMessage());
        }

        $code = (int) ($curl->get_info()['http_code'] ?? 0);

        // 401/403 means the credentials were rejected, which callers — notably the Backoffice Key
        // admin setting — must tell apart from a transport hiccup or a malformed response.
        if ($code === 401 || $code === 403) {
            throw new moodle_exception('api:error_unauthorized', 'paygw_ifthenpay', '', "HTTP $code");
        }
        if ($code >= 400) {
            throw new moodle_exception('api:error_http_status', 'paygw_ifthenpay', '', "HTTP $code: $body");
        }

        return (string) $body;
    }

    /**
     * GET and decode JSON.
     *
     * @param string $url URL.
     * @return mixed Decoded JSON (array|bool|null|scalar).
     * @throws moodle_exception On transport errors or invalid JSON.
     */
    protected function get_json(string $url) {
        $body = self::http('GET', $url, null, $this->timeout);
        $decoded = json_decode($body, true);
        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new moodle_exception('api:error_invalid_json_get', 'paygw_ifthenpay', '', json_last_error_msg());
        }
        return $decoded;
    }

    /**
     * POST a JSON payload, returning the decoded array or the raw body.
     *
     * Some endpoints answer with plain text ("OK"/"INVALID") rather than JSON, so a non-array
     * response is handed back as a string rather than treated as an error.
     *
     * @param string $url     Endpoint URL.
     * @param array<string, mixed> $payload Payload to be JSON-encoded.
     * @return array<array-key, mixed>|string Decoded array if JSON, else the raw body.
     * @throws moodle_exception On encoding or transport errors.
     */
    protected function post_json(string $url, array $payload) {
        $json = json_encode($payload);
        if ($json === false) {
            throw new moodle_exception('api:error_invalid_json_post', 'paygw_ifthenpay');
        }

        $body = self::http('POST', $url, $json, $this->timeout);
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : $body;
    }
}
