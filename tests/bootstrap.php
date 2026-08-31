<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('esc_url_raw')) {
    function esc_url_raw($url) {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
}

if (!function_exists('add_action')) {
    function add_action(...$args) {
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_request')) {
    function wp_remote_request($url, $args = array()) {
        return new WP_Error('http_request_failed', 'No HTTP transport available in tests.');
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['__test_transients'][$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['__test_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['__test_transients'][$key]);
        return true;
    }
}

class WP_Error {
    private $message;
    public function __construct($code = '', $message = '') {
        $this->message = $message;
    }
    public function get_error_message() {
        return $this->message;
    }
}

$GLOBALS['__test_transients'] = array();

$plugin_dir = dirname(__DIR__) . '/wp-content/plugins/masjid-app';

require_once $plugin_dir . '/vendor-prefixed/autoload.php';
require_once $plugin_dir . '/includes/class-firebase-exceptions.php';
require_once $plugin_dir . '/includes/class-firebase-client.php';
require_once $plugin_dir . '/includes/class-firebase.php';
require_once $plugin_dir . '/includes/class-push-notifications.php';
