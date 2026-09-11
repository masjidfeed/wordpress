<?php
/**
 * Bounded request tracing for the Masjid App REST API.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_API_Trace {

    const ENABLED_OPTION = 'masjidfeed_api_trace_enabled';
    const ENTRIES_OPTION = 'masjidfeed_api_trace_entries';
    const MAX_ENTRIES = 100;

    private $started_at = array();

    public function __construct() {
        add_filter('rest_request_before_callbacks', array($this, 'start_trace'), 10, 3);
        add_filter('rest_request_after_callbacks', array($this, 'finish_trace'), 10, 3);
    }

    public static function is_enabled() {
        return '1' === get_option(self::ENABLED_OPTION, '0');
    }

    public static function set_enabled($enabled) {
        update_option(self::ENABLED_OPTION, $enabled ? '1' : '0', false);
    }

    public static function get_entries() {
        $entries = get_option(self::ENTRIES_OPTION, array());
        return is_array($entries) ? $entries : array();
    }

    public static function clear() {
        delete_option(self::ENTRIES_OPTION);
    }

    public static function record_push($target_type, array $payload, $status, $duration_ms, $error_code = '') {
        if (!self::is_enabled()) {
            return;
        }

        self::add_entry(array(
            'id' => wp_generate_uuid4(),
            'timestamp' => gmdate('c'),
            'method' => 'push',
            'route' => 'firebase/' . sanitize_key($target_type),
            'status' => absint($status),
            'durationMs' => round((float) $duration_ms, 1),
            'errorCode' => sanitize_text_field($error_code),
            'appCheck' => 'n/a',
            'client' => '',
            'clientName' => '',
            'clientVersion' => '',
            'userAgent' => '',
            'parameters' => array(),
            'payload' => self::redact($payload),
        ));
    }

    public function start_trace($response, $handler, $request) {
        if (self::is_enabled() && $this->is_masjid_feed_request($request)) {
            $this->started_at[spl_object_id($request)] = microtime(true);
        }

        return $response;
    }

    public function finish_trace($response, $handler, $request) {
        $request_id = spl_object_id($request);
        if (!isset($this->started_at[$request_id])) {
            return $response;
        }

        $started_at = $this->started_at[$request_id];
        unset($this->started_at[$request_id]);

        $status = 200;
        $error_code = '';
        $firebase_details = array();
        if (is_wp_error($response)) {
            $error_code = (string) $response->get_error_code();
            $error_data = $response->get_error_data();
            $status = is_array($error_data) && isset($error_data['status']) ? absint($error_data['status']) : 500;
            if (is_array($error_data) && !empty($error_data['firebase'])) {
                $firebase_details = (array) $error_data['firebase'];
            }
        } elseif ($response instanceof WP_HTTP_Response) {
            $status = $response->get_status();
        }

        $address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $user_agent = substr(sanitize_text_field($request->get_header('user-agent')), 0, 255);
        $client = Masjid_Feed_REST_API::parse_client_user_agent($user_agent);
        $entry = array(
            'id' => wp_generate_uuid4(),
            'timestamp' => gmdate('c'),
            'method' => sanitize_key($request->get_method()),
            'route' => sanitize_text_field($request->get_route()),
            'status' => $status,
            'durationMs' => round((microtime(true) - $started_at) * 1000, 1),
            'errorCode' => $error_code,
            'appCheck' => '' !== trim((string) $request->get_header('x-firebase-appcheck')) ? 'present' : 'missing',
            'client' => substr(hash('sha256', $address), 0, 12),
            'clientName' => $client['name'] ?? '',
            'clientVersion' => $client['version'] ?? '',
            'userAgent' => $user_agent,
            'parameters' => self::redact($request->get_params()),
        );
        if ($firebase_details) {
            $entry['payload'] = self::redact($firebase_details);
        }

        self::add_entry($entry);

        return $response;
    }

    private static function add_entry(array $entry) {
        $entries = self::get_entries();
        array_unshift($entries, $entry);
        update_option(self::ENTRIES_OPTION, array_slice($entries, 0, self::MAX_ENTRIES), false);
    }

    private function is_masjid_feed_request($request) {
        return 0 === strpos($request->get_route(), '/masjid/v1/');
    }

    private static function redact($value, $depth = 0) {
        if ($depth >= 3) {
            return '[truncated]';
        }
        if (!is_array($value)) {
            return is_scalar($value) || null === $value
                ? substr(sanitize_text_field((string) $value), 0, 500)
                : '[' . gettype($value) . ']';
        }

        $redacted = array();
        foreach (array_slice($value, 0, 20, true) as $key => $item) {
            $safe_key = sanitize_key((string) $key);
            $display_key = substr(sanitize_text_field((string) $key), 0, 100);
            if (preg_match('/token|authorization|password|secret|credential|cookie|key/', $safe_key)) {
                $redacted[$display_key] = '[redacted]';
                continue;
            }
            $redacted[$display_key] = self::redact($item, $depth + 1);
        }
        return $redacted;
    }
}