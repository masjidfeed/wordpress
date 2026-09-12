<?php
/**
 * Firebase gateway built on the lightweight Masjid_Feed_Firebase_Client.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Firebase {

    private $client;

    public function __construct() {
        $credentials = Masjid_Feed_Credential_Store::get();
        if (is_wp_error($credentials)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal control-flow exception; any display escapes the message.
            throw new RuntimeException($credentials->get_error_message());
        }
        $this->client = new Masjid_Feed_Firebase_Client($credentials);
    }

    public function verify_app_check($token, array $allowed_app_ids) {
        $verified = $this->client->verify_app_check_token($token);
        if (!in_array($verified['app_id'], $allowed_app_ids, true)) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- static translated string; never rendered unescaped.
            throw new RuntimeException(__('The App Check token belongs to an unconfigured Firebase application.', 'masjidfeed-app'));
        }
        return $verified['app_id'];
    }

    public function subscribe($token) {
        return $this->client->subscribe_to_topic($this->topic(), $token);
    }

    public function unsubscribe($token) {
        return $this->client->unsubscribe_from_topic($this->topic(), $token);
    }

    public function send_topic(array $payload) {
        return $this->send('topic', $this->topic(), $payload);
    }

    public function send_test($token, array $payload) {
        return $this->send('token', $token, $payload);
    }

    public function validate() {
        $payload = array(
            'title' => __('MasjidFeed App Firebase validation', 'masjidfeed-app'),
            'body' => __('Configuration validated successfully.', 'masjidfeed-app'),
            'data' => array('type' => 'validation'),
        );
        return $this->client->send_message($this->message('topic', $this->topic(), $payload), true);
    }

    /**
     * Runs the same calls a device registration performs and validates the
     * stored Firebase configuration files, returning a per-item checklist:
     * each entry has 'label', 'status' (pass|fail|skipped), 'detail', and
     * 'response' (raw HTTP response body on failure).
     */
    public static function validate_configuration(): array {
        $checks = array();
        $opts = Masjid_Feed_Settings::get_all_options();

        $credentials = Masjid_Feed_Credential_Store::get();
        if (is_wp_error($credentials)) {
            $checks[] = array(
                'label' => __('Stored service-account JSON', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => $credentials->get_error_message(),
                'response' => '',
            );
            $credentials = null;
        } else {
            $checks[] = self::check_service_account($credentials);
        }

        $client = is_array($credentials) ? self::client_from_credentials($credentials) : null;

        $checks[] = self::check_ios_config($opts);
        $checks[] = self::check_android_config($opts);
        $checks[] = self::check_project_consistency($opts, $credentials);

        $token_ok = false;
        if ($client instanceof Masjid_Feed_Firebase_Client) {
            try {
                $client->access_token(true);
                $checks[] = array(
                    'label' => __('Firebase OAuth access token', 'masjidfeed-app'),
                    'status' => 'pass',
                    'detail' => sprintf(
                        /* translators: %s: OAuth token endpoint URL. */
                        __('POST %s returned a fresh access token. This is the request made when a device registers.', 'masjidfeed-app'),
                        Masjid_Feed_Firebase_Client::OAUTH_TOKEN_URL
                    ),
                    'response' => '',
                );
                $token_ok = true;
            } catch (Throwable $exception) {
                $checks[] = array(
                    'label' => __('Firebase OAuth access token', 'masjidfeed-app'),
                    'status' => 'fail',
                    'detail' => sprintf('POST %s → %s', Masjid_Feed_Firebase_Client::OAUTH_TOKEN_URL, $exception->getMessage()),
                    'response' => $exception instanceof Masjid_Feed_Firebase_Exception ? $exception->response_body() : '',
                );
            }

            $checks[] = self::check_app_check_keys($client);
            $checks[] = self::check_fcm_validate_send($token_ok);
        } else {
            $checks[] = self::skipped(__('Firebase OAuth access token', 'masjidfeed-app'), __('Skipped: no usable stored service account.', 'masjidfeed-app'));
            $checks[] = self::skipped(__('Firebase App Check public keys', 'masjidfeed-app'), __('Skipped: no usable stored service account.', 'masjidfeed-app'));
            $checks[] = self::skipped(__('FCM validate-only send', 'masjidfeed-app'), __('Skipped: no usable stored service account.', 'masjidfeed-app'));
        }

        return $checks;
    }

    private static function check_service_account(array $credentials): array {
        foreach (array('project_id', 'client_email', 'private_key') as $field) {
            if (empty($credentials[$field]) || !is_string($credentials[$field])) {
                return array(
                    'label' => __('Stored service-account JSON', 'masjidfeed-app'),
                    'status' => 'fail',
                    'detail' => sprintf(
                        /* translators: %s: comma-separated field names. */
                        __('The stored service account is missing these fields: %s.', 'masjidfeed-app'),
                        $field
                    ),
                    'response' => '',
                );
            }
        }
        $key = openssl_pkey_get_private($credentials['private_key']);
        if (false === $key) {
            return array(
                'label' => __('Stored service-account JSON', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => __('The stored service-account private key could not be parsed. Re-upload the service-account JSON.', 'masjidfeed-app'),
                'response' => '',
            );
        }
        return array(
            'label' => __('Stored service-account JSON', 'masjidfeed-app'),
            'status' => 'pass',
            'detail' => sprintf(
                /* translators: 1: Firebase project ID, 2: service-account email. */
                __('Project %1$s, service account %2$s.', 'masjidfeed-app'),
                (string) $credentials['project_id'],
                (string) $credentials['client_email']
            ),
            'response' => '',
        );
    }

    private static function client_from_credentials(array $credentials): ?Masjid_Feed_Firebase_Client {
        foreach (array('project_id', 'client_email', 'private_key') as $field) {
            if (empty($credentials[$field]) || !is_string($credentials[$field])) {
                return null;
            }
        }
        if (false === openssl_pkey_get_private($credentials['private_key'])) {
            return null;
        }
        return new Masjid_Feed_Firebase_Client($credentials);
    }

    private static function check_ios_config(array $opts): array {
        $config = is_array($opts['firebase_ios_config'] ?? null) ? $opts['firebase_ios_config'] : array();
        $missing = array_diff(array('appId', 'senderId', 'projectId', 'apiKey', 'bundleId'), array_keys(array_filter($config)));
        if ($missing) {
            return array(
                'label' => __('iOS GoogleService-Info.plist configuration', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => sprintf(
                    /* translators: %s: comma-separated field names. */
                    __('The stored iOS plist configuration is missing these fields: %s.', 'masjidfeed-app'),
                    implode(', ', $missing)
                ),
                'response' => '',
            );
        }
        return array(
            'label' => __('iOS GoogleService-Info.plist configuration', 'masjidfeed-app'),
            'status' => 'pass',
            'detail' => sprintf(
                /* translators: 1: bundle ID, 2: Firebase project ID. */
                __('App %1$s in project %2$s.', 'masjidfeed-app'),
                (string) $config['bundleId'],
                (string) $config['projectId']
            ),
            'response' => '',
        );
    }

    private static function check_android_config(array $opts): array {
        $config = is_array($opts['firebase_android_config'] ?? null) ? $opts['firebase_android_config'] : array();
        $missing = array_diff(array('appId', 'senderId', 'projectId', 'apiKey', 'packageName'), array_keys(array_filter($config)));
        if ($missing) {
            return array(
                'label' => __('Android google-services.json configuration', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => sprintf(
                    /* translators: %s: comma-separated field names. */
                    __('The stored Android google-services.json is missing these fields: %s.', 'masjidfeed-app'),
                    implode(', ', $missing)
                ),
                'response' => '',
            );
        }
        return array(
            'label' => __('Android google-services.json configuration', 'masjidfeed-app'),
            'status' => 'pass',
            'detail' => sprintf(
                /* translators: 1: package name, 2: Firebase project ID. */
                __('App %1$s in project %2$s.', 'masjidfeed-app'),
                (string) $config['packageName'],
                (string) $config['projectId']
            ),
            'response' => '',
        );
    }

    private static function check_project_consistency(array $opts, $credentials): array {
        $project_ids = array(
            'stored setting' => (string) ($opts['firebase_project_id'] ?? ''),
            'iOS plist' => (string) ($opts['firebase_ios_config']['projectId'] ?? ''),
            'Android JSON' => (string) ($opts['firebase_android_config']['projectId'] ?? ''),
        );
        if (is_array($credentials) && !empty($credentials['project_id'])) {
            $project_ids['service account'] = (string) $credentials['project_id'];
        }
        $unique = array_values(array_unique(array_filter($project_ids)));
        if (count($unique) > 1) {
            $listed = array();
            foreach ($project_ids as $source => $project) {
                if ('' !== $project) {
                    $listed[] = sprintf('%s: %s', $source, $project);
                }
            }
            return array(
                'label' => __('Firebase project consistency', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => sprintf(
                    /* translators: %s: comma-separated list of project IDs per source. */
                    __('The configured files reference different Firebase projects (%s). They must all use the same project.', 'masjidfeed-app'),
                    implode(', ', $listed)
                ),
                'response' => '',
            );
        }
        return array(
            'label' => __('Firebase project consistency', 'masjidfeed-app'),
            'status' => 'pass',
            'detail' => sprintf(
                /* translators: %s: Firebase project ID. */
                __('All configured files use project %s.', 'masjidfeed-app'),
                $unique[0] ?? __('(none)', 'masjidfeed-app')
            ),
            'response' => '',
        );
    }

    private static function check_app_check_keys(Masjid_Feed_Firebase_Client $client): array {
        try {
            $keys = $client->app_check_keys(false);
            return array(
                'label' => __('Firebase App Check public keys', 'masjidfeed-app'),
                'status' => 'pass',
                'detail' => sprintf(
                    /* translators: 1: JWKS URL, 2: number of keys. */
                    __('GET %1$s returned %2$d public signing keys.', 'masjidfeed-app'),
                    Masjid_Feed_Firebase_Client::APP_CHECK_JWKS_URL,
                    count($keys)
                ),
                'response' => '',
            );
        } catch (Throwable $exception) {
            return array(
                'label' => __('Firebase App Check public keys', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => sprintf('GET %s → %s', Masjid_Feed_Firebase_Client::APP_CHECK_JWKS_URL, $exception->getMessage()),
                'response' => $exception instanceof Masjid_Feed_Firebase_Exception ? $exception->response_body() : '',
            );
        }
    }

    private static function check_fcm_validate_send(bool $token_ok): array {
        if (!$token_ok) {
            return self::skipped(__('FCM validate-only send', 'masjidfeed-app'), __('Skipped because the OAuth access token could not be fetched.', 'masjidfeed-app'));
        }
        try {
            (new Masjid_Feed_Firebase())->validate();
            return array(
                'label' => __('FCM validate-only send', 'masjidfeed-app'),
                'status' => 'pass',
                'detail' => sprintf(
                    /* translators: %s: Firebase project ID. */
                    __('The FCM API accepted a validate-only message for project %s.', 'masjidfeed-app'),
                    (string) Masjid_Feed_Settings::get_option('firebase_project_id')
                ),
                'response' => '',
            );
        } catch (Throwable $exception) {
            return array(
                'label' => __('FCM validate-only send', 'masjidfeed-app'),
                'status' => 'fail',
                'detail' => $exception->getMessage(),
                'response' => $exception instanceof Masjid_Feed_Firebase_Exception ? $exception->response_body() : '',
            );
        }
    }

    private static function skipped(string $label, string $detail): array {
        return array('label' => $label, 'status' => 'skipped', 'detail' => $detail, 'response' => '');
    }

    private function send($target_type, $target, array $payload) {
        $started_at = microtime(true);
        try {
            $result = $this->client->send_message($this->message($target_type, $target, $payload));
            $this->trace_push($target_type, $payload, 200, $started_at);
            return $result;
        } catch (Throwable $exception) {
            $this->trace_push($target_type, $payload, 502, $started_at, get_class($exception));
            throw $exception;
        }
    }

    private function trace_push($target_type, array $payload, $status, $started_at, $error_code = '') {
        if (!class_exists('Masjid_Feed_API_Trace')) {
            return;
        }
        try {
            Masjid_Feed_API_Trace::record_push($target_type, $payload, $status, (microtime(true) - $started_at) * 1000, $error_code);
        } catch (Throwable) {
        }
    }

    private function message($target_type, $target, array $payload): array {
        $notification = array(
            'title' => (string) $payload['title'],
            'body' => (string) $payload['body'],
        );
        $image_url = esc_url_raw((string) ($payload['image'] ?? ''));
        if ('' !== $image_url) {
            $notification['image'] = $image_url;
        }

        $message = array(
            'data' => array_map('strval', $payload['data']),
            'notification' => $notification,
        );
        $message[$target_type] = $target;
        if ('' !== $image_url) {
            $message['apns'] = array(
                'payload' => array('aps' => array('mutable-content' => 1)),
                'fcm_options' => array('image' => $image_url),
            );
        }
        return array('message' => $message);
    }

    private function topic() {
        $masjid_id = Masjid_Feed_Settings::get_option('masjid_id');
        return substr('masjid_' . preg_replace('/[^A-Za-z0-9-_.~%]/', '_', (string) $masjid_id), 0, 900);
    }
}
