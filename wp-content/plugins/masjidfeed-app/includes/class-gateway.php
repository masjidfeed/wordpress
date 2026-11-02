<?php
/**
 * Client for the hosted MasjidFeed push gateway (masjidfeed-app.gsf8.org).
 *
 * The gateway is the sole holder of the Firebase project service account.
 * Sites self-register (register → serve challenge → verify) and then send
 * topic notifications with the per-site gateway secret. See
 * docs/gateway.md and push-gateway/README.md for the endpoint contracts.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Gateway_Exception extends RuntimeException {

    private $retry_after;
    private $http_status = 0;
    private $response_body = '';
    private $error_code = '';

    public function __construct($message = '', $code = 0, ?Throwable $previous = null, ?int $retry_after = null) {
        parent::__construct($message, $code, $previous);
        $this->retry_after = $retry_after;
    }

    public function is_retryable(): bool {
        return false;
    }

    public function retry_after(): ?int {
        return $this->retry_after;
    }

    public function set_response_details(int $http_status, string $response_body): void {
        $this->http_status = $http_status;
        $this->response_body = substr($response_body, 0, 2000);
        // The gateway's error envelope is {"error": {"code", "message"}} and
        // the code (not the status) distinguishes payloads that share a
        // status, e.g. 400 validation vs 400 verification_failed, or the two
        // 429 rate_limit / daily_quota reasons.
        $decoded = json_decode($response_body, true);
        $code = is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
        $this->error_code = is_string($code) ? $code : '';
    }

    public function http_status(): int {
        return $this->http_status;
    }

    public function response_body(): string {
        return $this->response_body;
    }

    public function error_code(): string {
        return $this->error_code;
    }
}

class Masjid_Feed_Gateway_Connection_Failed extends Masjid_Feed_Gateway_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Gateway_Invalid_Request extends Masjid_Feed_Gateway_Exception {
}

class Masjid_Feed_Gateway_Authentication_Error extends Masjid_Feed_Gateway_Exception {
}

class Masjid_Feed_Gateway_Not_Found extends Masjid_Feed_Gateway_Exception {
}

class Masjid_Feed_Gateway_Forbidden extends Masjid_Feed_Gateway_Exception {
}

class Masjid_Feed_Gateway_Rate_Limited extends Masjid_Feed_Gateway_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Gateway_Server_Error extends Masjid_Feed_Gateway_Exception {
    public function is_retryable(): bool {
        return true;
    }
}

class Masjid_Feed_Gateway {

    const GATEWAY_URL = 'https://masjidfeed-app.gsf8.org';
    const REQUEST_TIMEOUT = 30;
    // The gateway only delivers deep links that start with the prefix
    // registered for the site. The plugin registers the MasjidApp URI scheme
    // and only saves deep-link templates that use it.
    const DEEP_LINK_PREFIX = 'masjidapp://';

    private $transport;

    public function __construct(?callable $transport = null) {
        $this->transport = $transport ?? array($this, 'wordpress_transport');
    }

    /**
     * Register this site with the gateway. The plugin stores a one-time
     * proof challenge plus a random fetch token before calling this; the
     * gateway fetches the challenge by callback — presenting the fetch
     * token in the X-MasjidFeed-Fetch-Token header, which the verification
     * endpoint requires — and, on match, activates the site and returns the
     * gateway secret (shown exactly once) in the same response. When the
     * callback fails the site stays pending: the response carries no secret
     * and onboarding completes through verify_site() instead.
     */
    public function register_site(string $challenge, string $fetch_token): array {
        $body = array(
            'url' => home_url(),
            'challenge' => $challenge,
            'fetch_token' => $fetch_token,
            'name' => (string) Masjid_Feed_Settings::get_option('masjid_name'),
            'deep_link_prefix' => self::DEEP_LINK_PREFIX,
        );
        $location = $this->registration_location();
        if (null !== $location) {
            $body['location'] = $location;
        }
        $data = $this->request(
            'POST',
            '/v1/sites',
            array(),
            $body
        );
        if (empty($data['site_id'])) {
            throw new Masjid_Feed_Gateway_Exception(__('The push service returned an incomplete registration response.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return array(
            'site_id' => (string) $data['site_id'],
            'status' => (string) ($data['status'] ?? ''),
            'gateway_secret' => (string) ($data['gateway_secret'] ?? ''),
        );
    }

    /**
     * The mosque coordinates registered with the Muslim Prayer Times plugin
     * — the lat/lng settings its /wp-json/muslim-prayer-times/v1/salah-api
     * endpoint exposes under "location". Sent as the optional registration
     * "location" so the gateway knows where the masjid is. Returns null
     * (and the field is omitted) when the plugin is absent, its coordinates
     * are unset, or the values are outside the ranges the gateway accepts.
     */
    private function registration_location(): ?array {
        $opts = get_option('muslprti_settings', array());
        $opts = is_array($opts) ? $opts : array();
        if (
            !isset($opts['lat'], $opts['lng'])
            || !is_numeric($opts['lat']) || !is_numeric($opts['lng'])
        ) {
            return null;
        }
        $latitude = (float) $opts['lat'];
        $longitude = (float) $opts['lng'];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }
        return array('latitude' => $latitude, 'longitude' => $longitude);
    }

    /**
     * Retry path for a pending site whose registration callback failed: the
     * gateway re-fetches the challenge from the site's verification endpoint
     * (presenting the fetch token) and returns the gateway secret (shown
     * exactly once) on match. Only the party holding the fetch token — this
     * site's plugin — can complete verification.
     */
    public function verify_site(string $site_id, string $fetch_token): array {
        $data = $this->request(
            'POST',
            '/v1/sites/' . rawurlencode($site_id) . '/verify',
            array(),
            array('fetch_token' => $fetch_token)
        );
        // Verification always completes onboarding: a response without the
        // secret must fail loudly instead of storing an empty secret that
        // keeps the registration looking uncompleted (or, worse, reports
        // success to the admin while nothing was stored).
        if (empty($data['site_id']) || '' === (string) ($data['gateway_secret'] ?? '')) {
            throw new Masjid_Feed_Gateway_Exception(__('The push service returned an incomplete verification response.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return array(
            'site_id' => (string) $data['site_id'],
            'status' => (string) ($data['status'] ?? ''),
            'gateway_secret' => (string) ($data['gateway_secret'] ?? ''),
        );
    }

    /**
     * Proxy the app's client-side subscription: the gateway verifies the
     * App Check token against the central Firebase project and returns the
     * canonical topic for the app to subscribe to itself via
     * FirebaseMessaging.subscribeToTopic.
     */
    public function issue_topic_key(string $app_check_token): array {
        $site_id = Masjid_Feed_Settings::gateway_site_id();
        if ('' === $site_id) {
            throw new Masjid_Feed_Gateway_Exception(__('This site is not registered with the MasjidFeed push service.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return $this->app_check_topic_request(
            '/v1/topic-key',
            $app_check_token,
            array('site_id' => $site_id),
            __('The push service did not issue a topic key.', 'masjidfeed-app')
        );
    }

    /**
     * Proxy the app's device registration to the gateway. The gateway
     * verifies the App Check token against the central Firebase project and
     * subscribes the app's FCM registration token to the site's canonical
     * topic server-side via FCM's Instance ID service; the token is passed
     * through and stored nowhere. Both registration paths answer with the
     * topic-key shape, so the app keeps re-requesting on the same cadence —
     * after a rotation the next registration moves the device onto the new
     * topic.
     */
    public function register_device(string $app_check_token, string $fcm_token): array {
        $site_id = Masjid_Feed_Settings::gateway_site_id();
        if ('' === $site_id) {
            throw new Masjid_Feed_Gateway_Exception(__('This site is not registered with the MasjidFeed push service.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return $this->app_check_topic_request(
            '/v1/devices',
            $app_check_token,
            array('site_id' => $site_id, 'token' => $fcm_token),
            __('The push service did not register the device.', 'masjidfeed-app')
        );
    }

    /**
     * Shared App-Check-gated topic call: /v1/topic-key and /v1/devices both
     * answer with the topic-key shape; a response without a topic is
     * incomplete and must fail loudly instead of looking like a successful
     * registration.
     */
    private function app_check_topic_request(string $path, string $app_check_token, array $body, string $failure_message): array {
        $data = $this->request('POST', $path, array('X-Firebase-AppCheck' => $app_check_token), $body);
        if (empty($data['topic'])) {
            throw new Masjid_Feed_Gateway_Exception($failure_message); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return $data;
    }

    /**
     * Send one notification through the gateway. Accepts the payload shape
     * built by Masjid_Feed_Push_Notifications::build_payload() and returns
     * the send_id (the FCM message name).
     */
    public function send(array $payload): string {
        $site_id = Masjid_Feed_Settings::gateway_site_id();
        $secret = Masjid_Feed_Settings::gateway_secret();
        if ('' === $site_id || '' === $secret) {
            throw new Masjid_Feed_Gateway_Exception(__('This site is not registered with the MasjidFeed push service.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        $title = (string) ($payload['title'] ?? '');
        // The gateway rejects titles over 100 bytes, so cut on a character
        // boundary and append an ellipsis that keeps the total within limit.
        if (strlen($title) > 100) {
            $title = (function_exists('mb_strcut') ? mb_strcut($title, 0, 97) : substr($title, 0, 97)) . '…';
        }
        $body = (string) ($payload['body'] ?? '');
        // Bodies are capped at 300 bytes at the gateway; the queue builds
        // them with a character-based excerpt, so a multibyte body can
        // still exceed the byte limit and is cut here on a char boundary.
        if (strlen($body) > 300) {
            $body = (function_exists('mb_strcut') ? mb_strcut($body, 0, 297) : substr($body, 0, 297)) . '…';
        }
        $data = $this->request(
            'POST',
            '/v1/sites/' . rawurlencode($site_id) . '/messages',
            array('Authorization' => 'Bearer ' . $secret),
            array(
                'notification_id' => (string) ($payload['data']['notificationId'] ?? ''),
                'title' => $title,
                'body' => $body,
                // The remaining fields mirror the direct-FCM message so both
                // channels carry identical properties: the gateway maps them
                // onto notification.image, the APNs rich-push block, and the
                // camelCase data keys the app reads.
                'deep_link' => (string) ($payload['data']['deepLink'] ?? ''),
                'image' => (string) ($payload['image'] ?? ''),
                'masjid_id' => (string) ($payload['data']['masjidId'] ?? ''),
                'post_id' => (string) ($payload['data']['postId'] ?? ''),
                'type' => (string) ($payload['data']['type'] ?? ''),
            )
        );
        $send_id = (string) ($data['send_id'] ?? '');
        if ('' === $send_id) {
            throw new Masjid_Feed_Gateway_Exception(__('The push service returned no send ID.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return $send_id;
    }

    /**
     * Debug tool: verify this site's credentials with the gateway without
     * sending anything. Returns true when the secret is valid and the site
     * is active; anything else throws.
     */
    public function validate_connection(): bool {
        $data = $this->authorized_request('POST', '/validate', null);
        return !empty($data['ok']);
    }

    /**
     * Rotate this site's topic keys: the gateway issues a new topic secret
     * and bumps the version. Devices pick up the new topic on their next
     * registration call, and sends keep fanning out to the previous version
     * for the rotation grace window. Returns the new topic version.
     */
    public function rotate_topic(): int {
        $data = $this->authorized_request('POST', '/rotate', null);
        if (!isset($data['topic_version'])) {
            throw new Masjid_Feed_Gateway_Exception(__('The push service returned no topic version.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return (int) $data['topic_version'];
    }

    /**
     * Fetch this site's registration audit entries from the gateway (most
     * recent first, capped at 50).
     */
    public function site_audit(): array {
        $entries = $this->authorized_request('GET', '/audit', null);
        return is_array($entries) ? $entries : array();
    }

    /**
     * Call a bearer-authenticated site endpoint: /validate, /rotate, or
     * /audit under /v1/sites/{site_id}. Requires a completed registration;
     * $body is omitted for the GET endpoints.
     */
    private function authorized_request(string $method, string $suffix, ?array $body): array {
        $site_id = Masjid_Feed_Settings::gateway_site_id();
        $secret = Masjid_Feed_Settings::gateway_secret();
        if ('' === $site_id || '' === $secret) {
            throw new Masjid_Feed_Gateway_Exception(__('This site is not registered with the MasjidFeed push service.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        }
        return $this->request(
            $method,
            '/v1/sites/' . rawurlencode($site_id) . $suffix,
            array('Authorization' => 'Bearer ' . $secret),
            $body
        );
    }

    /**
     * Send an HTTP request to a gateway endpoint, returning the decoded
     * JSON response body. $headers carries the endpoint's authentication
     * (e.g. a bearer token or App Check token) and may be empty:
     * registration is validated by callback to the registered URL instead.
     * $body is JSON-encoded and omitted when null.
     */
    private function request(string $method, string $path, array $headers, ?array $body = null): array {
        $options = array(
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => $headers + array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
        );
        if (null !== $body) {
            $encoded = wp_json_encode($body);
            if (!is_string($encoded)) {
                throw new Masjid_Feed_Gateway_Invalid_Request(__('The notification payload could not be encoded.', 'masjidfeed-app')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
            }
            $options['body'] = $encoded;
        }
        try {
            $response = ($this->transport)($method, self::GATEWAY_URL . $path, $options);
        } catch (Throwable $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
            throw new Masjid_Feed_Gateway_Connection_Failed($exception->getMessage());
        }
        $status = (int) ($response['status'] ?? 0);
        $raw = (string) ($response['body'] ?? '');
        $headers = is_array($response['headers'] ?? null) ? $response['headers'] : array();
        $data = json_decode($raw, true);
        if (200 <= $status && $status < 300 && is_array($data)) {
            return $data;
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
        throw $this->error_for_status($status, $raw, $headers);
    }

    private function error_for_status(int $status, string $body, array $headers = array()): Masjid_Feed_Gateway_Exception {
        $decoded = json_decode($body, true);
        $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? '') : '';
        if ('' === $message) {
            $message = sprintf('The push service returned HTTP %d.', $status);
        }
        $classes = array(
            400 => Masjid_Feed_Gateway_Invalid_Request::class,
            401 => Masjid_Feed_Gateway_Authentication_Error::class,
            403 => Masjid_Feed_Gateway_Forbidden::class,
            404 => Masjid_Feed_Gateway_Not_Found::class,
            410 => Masjid_Feed_Gateway_Invalid_Request::class,
            429 => Masjid_Feed_Gateway_Rate_Limited::class,
        );
        $class = $classes[$status] ?? Masjid_Feed_Gateway_Server_Error::class;
        $retry_after = 429 === $status ? $this->retry_after_header($headers) : null;
        $exception = new $class($message, 0, null, $retry_after);
        $exception->set_response_details($status, $body);
        return $exception;
    }

    private function retry_after_header(array $headers): ?int {
        $value = '';
        foreach ($headers as $name => $header_value) {
            if (is_string($name) && strtolower($name) === 'retry-after') {
                $value = is_array($header_value) ? (string) reset($header_value) : (string) $header_value;
                break;
            }
        }
        return '' !== $value && is_numeric($value) ? max(0, (int) $value) : null;
    }

    /**
     * Default transport built on the WordPress HTTP API. Same return shape
     * as Masjid_Feed_Firebase_Client::wordpress_transport().
     */
    public function wordpress_transport(string $method, string $url, array $options): array {
        $options['method'] = $method;
        $response = wp_remote_request($url, $options);
        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
            throw new Masjid_Feed_Gateway_Connection_Failed($response->get_error_message());
        }
        $headers = array();
        foreach ((array) ($response['headers'] ?? array()) as $name => $value) {
            $headers[strtolower((string) $name)] = $value;
        }
        return array(
            'status' => (int) ($response['response']['code'] ?? 0),
            'headers' => $headers,
            'body' => (string) ($response['body'] ?? ''),
        );
    }
}
