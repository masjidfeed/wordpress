<?php
/**
 * Minimal Firebase client speaking the FCM HTTP v1, Instance ID, and App Check APIs
 * using WordPress HTTP and the scoped firebase/php-jwt library.
 */

if (!defined('ABSPATH')) { exit; }

use Masjid_Feed\Dependencies\Firebase\JWT\BeforeValidException;
use Masjid_Feed\Dependencies\Firebase\JWT\ExpiredException;
use Masjid_Feed\Dependencies\Firebase\JWT\JWK;
use Masjid_Feed\Dependencies\Firebase\JWT\JWT;
use Masjid_Feed\Dependencies\Firebase\JWT\Key;

class Masjid_Feed_Firebase_Client {

    const OAUTH_TOKEN_URL = 'https://oauth2.googleapis.com/token';
    const FCM_SEND_URL = 'https://fcm.googleapis.com/v1/projects/';
    const IID_URL = 'https://iid.googleapis.com';
    const APP_CHECK_JWKS_URL = 'https://firebaseappcheck.googleapis.com/v1/jwks';
    const APP_CHECK_ISSUER_PREFIX = 'https://firebaseappcheck.googleapis.com/';
    const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging https://www.googleapis.com/auth/cloud-platform';
    const TOKEN_CACHE_KEY = 'masjidfeed_firebase_access_token';
    const JWKS_CACHE_KEY = 'masjidfeed_firebase_app_check_jwks';
    const REQUEST_TIMEOUT = 15;

    private $service_account;
    private $transport;
    private $cache_get;
    private $cache_set;
    private $clock;

    public function __construct(
        array $service_account,
        ?callable $transport = null,
        ?callable $cache_get = null,
        ?callable $cache_set = null,
        ?callable $clock = null
    ) {
        foreach (array('project_id', 'client_email', 'private_key') as $field) {
            if (empty($service_account[$field])) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- static format with whitelisted field name; never rendered unescaped.
                throw new Masjid_Feed_Firebase_Exception(sprintf('The Firebase service account is missing the "%s" field.', $field));
            }
        }
        $this->service_account = $service_account;
        $this->transport = $transport ?? array($this, 'wordpress_transport');
        $this->cache_get = $cache_get ?? 'get_transient';
        $this->cache_set = $cache_set ?? 'set_transient';
        $this->clock = $clock ?? 'time';
    }

    public function wordpress_transport(string $method, string $url, array $options): array {
        $response = wp_remote_request($url, $options);
        if (is_wp_error($response)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
            throw new Masjid_Feed_Firebase_Api_Connection_Failed($response->get_error_message());
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

    public function access_token(): string {
        $cached = ($this->cache_get)(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && '' !== $cached) {
            return $cached;
        }
        $now = ($this->clock)();
        $assertion = JWT::encode(array(
            'iss' => $this->service_account['client_email'],
            'sub' => $this->service_account['client_email'],
            'aud' => self::OAUTH_TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => self::SCOPE,
        ), $this->service_account['private_key'], 'RS256');

        $response = ($this->transport)('POST', self::OAUTH_TOKEN_URL, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'body' => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ),
        ));
        if ($response['status'] >= 400) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message from a remote API response; any display escapes it.
            throw new Masjid_Feed_Firebase_Authentication_Error($this->error_message($response, 'Could not fetch a Firebase OAuth access token.'));
        }
        $data = json_decode($response['body'], true);
        if (empty($data['access_token'])) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message from a remote API response; any display escapes it.
            throw new Masjid_Feed_Firebase_Authentication_Error($this->error_message($response, 'The OAuth token response did not contain an access token.'));
        }
        ($this->cache_set)(self::TOKEN_CACHE_KEY, (string) $data['access_token'], max(60, (int) ($data['expires_in'] ?? 3600) - 60));
        return (string) $data['access_token'];
    }

    public function send_message(array $message, bool $validate_only = false): array {
        $body = array('message' => $message['message'] ?? $message);
        if ($validate_only) {
            $body['validate_only'] = true;
        }
        $response = $this->authorized_request(
            'POST',
            self::FCM_SEND_URL . $this->service_account['project_id'] . '/messages:send',
            $body
        );
        return $this->decode_json($response);
    }

    public function subscribe_to_topic(string $topic, string $registration_token): array {
        return $this->topic_action('/iid/v1:batchAdd', $topic, $registration_token);
    }

    public function unsubscribe_from_topic(string $topic, string $registration_token): array {
        return $this->topic_action('/iid/v1:batchRemove', $topic, $registration_token);
    }

    private function topic_action(string $path, string $topic, string $registration_token): array {
        $response = $this->authorized_request(
            'POST',
            self::IID_URL . $path,
            array('to' => '/topics/' . $topic, 'registration_tokens' => array($registration_token))
        );
        $data = $this->decode_json($response);
        $results = array();
        foreach ((array) ($data['results'] ?? array()) as $index => $token_result) {
            $results[$topic][(string) $registration_token] = empty($token_result)
                ? 'OK'
                : (string) ($token_result['error'] ?? 'UNKNOWN');
        }
        return $results;
    }

    public function verify_app_check_token(string $token): array {
        $keys = $this->app_check_keys(false);
        if (!$this->token_matches_keys($token, $keys)) {
            $keys = $this->app_check_keys(true);
        }
        try {
            $decoded = JWT::decode($token, $keys);
        } catch (ExpiredException | BeforeValidException $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- rethrown JWT library exception; any display escapes the message.
            throw new Masjid_Feed_Firebase_Invalid_App_Check_Token($exception->getMessage(), 0, $exception);
        } catch (Throwable $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- rethrown library exception; any display escapes the message.
            throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token($exception->getMessage(), 0, $exception);
        }
        $payload = array(
            'app_id' => (string) ($decoded->app_id ?? $decoded->sub ?? ''),
            'aud' => (array) ($decoded->aud ?? array()),
            'exp' => (int) ($decoded->exp ?? 0),
            'iat' => (int) ($decoded->iat ?? 0),
            'iss' => (string) ($decoded->iss ?? ''),
            'sub' => (string) ($decoded->sub ?? ''),
        );
        if (!in_array('projects/' . $this->service_account['project_id'], $payload['aud'], true)) {
            throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token('The "aud" claim must include the project ID.');
        }
        if (!str_starts_with($payload['iss'], self::APP_CHECK_ISSUER_PREFIX)) {
            throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token('The provided App Check token has incorrect "iss" (issuer) claim.');
        }
        return $payload;
    }

    private function app_check_keys(bool $force_refresh): array {
        $jwks_json = $force_refresh ? null : ($this->cache_get)(self::JWKS_CACHE_KEY);
        if (!is_string($jwks_json) || '' === $jwks_json) {
            $response = ($this->transport)('GET', self::APP_CHECK_JWKS_URL, array('timeout' => self::REQUEST_TIMEOUT));
            if ($response['status'] >= 400) {
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message from a remote API response; any display escapes it.
                throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token($this->error_message($response, 'Could not fetch the App Check public keys.'));
            }
            $jwks_json = $response['body'];
            ($this->cache_set)(self::JWKS_CACHE_KEY, $jwks_json, 3600);
        }
        $parsed = json_decode((string) $jwks_json, true);
        if (!is_array($parsed)) {
            throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token('The App Check public keys response was not valid JSON.');
        }
        try {
            return JWK::parseKeySet($parsed, 'RS256');
        } catch (Throwable $exception) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- rethrown library exception; any display escapes the message.
            throw new Masjid_Feed_Firebase_Failed_Verify_App_Check_Token($exception->getMessage(), 0, $exception);
        }
    }

    private function token_matches_keys(string $token, array $keys): bool {
        $parts = explode('.', $token);
        if (3 !== count($parts)) {
            return false;
        }
        $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
        $kid = is_array($header) ? (string) ($header['kid'] ?? '') : '';
        return '' !== $kid && array_key_exists($kid, $keys);
    }

    private function authorized_request(string $method, string $url, array $json_body): array {
        return ($this->transport)($method, $url, array(
            'timeout' => self::REQUEST_TIMEOUT,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->access_token(),
                'Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'application/json, text/plain;q=0.9',
            ),
            'body' => json_encode($json_body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
    }

    private function decode_json(array $response): array {
        if ($response['status'] >= 400) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- message from a remote API response; any display escapes it.
            throw $this->exception_for_response($response);
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data)) {
            $exception = new Masjid_Feed_Firebase_Server_Error($this->error_message($response, 'The Firebase API returned a malformed JSON response.'));
            $exception->set_response_details((int) $response['status'], (string) $response['body']);
            throw $exception;
        }
        return $data;
    }

    private function exception_for_response(array $response): Masjid_Feed_Firebase_Exception {
        $message = $this->error_message($response, 'The Firebase API request failed.');
        $retry_after = $this->parse_retry_after($this->header($response, 'Retry-After'));
        switch ($response['status']) {
            case 400:
                $exception = new Masjid_Feed_Firebase_Invalid_Message($message);
                break;
            case 401:
            case 403:
                $exception = new Masjid_Feed_Firebase_Authentication_Error($message);
                break;
            case 404:
                $exception = new Masjid_Feed_Firebase_Not_Found($message);
                break;
            case 429:
                $exception = new Masjid_Feed_Firebase_Quota_Exceeded($message, 0, null, $retry_after);
                break;
            case 500:
                $exception = new Masjid_Feed_Firebase_Server_Error($message);
                break;
            case 503:
                $exception = new Masjid_Feed_Firebase_Server_Unavailable($message, 0, null, $retry_after);
                break;
            default:
                $exception = new Masjid_Feed_Firebase_Exception($message, $response['status']);
                break;
        }
        $exception->set_response_details((int) $response['status'], (string) $response['body']);
        return $exception;
    }

    private function error_message(array $response, string $fallback): string {
        $data = json_decode($response['body'], true);
        if (is_array($data)) {
            $message = '';
            if (!empty($data['error']['message']) && is_string($data['error']['message'])) {
                $message = $data['error']['message'];
            } elseif (!empty($data['error_description']) && is_string($data['error_description'])) {
                $message = $data['error_description'];
            } elseif (!empty($data['error']) && is_string($data['error'])) {
                $message = $data['error'];
            }
            if ('' !== $message) {
                return sprintf('%s (HTTP %d)', $message, $response['status']);
            }
        }
        return sprintf('%s (HTTP %d)', $fallback, $response['status']);
    }

    private function header(array $response, string $name): ?string {
        foreach ($response['headers'] ?? array() as $key => $value) {
            if (0 === strcasecmp((string) $key, $name)) {
                return is_array($value) ? (string) ($value[0] ?? '') : (string) $value;
            }
        }
        return null;
    }

    private function parse_retry_after($value): ?int {
        if (!is_string($value) || '' === $value) {
            return null;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }
        $timestamp = strtotime($value);
        if (false === $timestamp) {
            return null;
        }
        return max(0, $timestamp - ($this->clock)());
    }
}
