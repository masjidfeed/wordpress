<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
define('MASJIDFEED_OPTION_KEY', 'masjidfeed_settings');
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
    public function get_error_code() {
        return $this->message ? $this->message : '';
    }
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

$GLOBALS['__test_options'] = [];
$GLOBALS['__test_posts'] = [];
$GLOBALS['__test_terms'] = [];
$GLOBALS['__test_terms_list'] = [];
$GLOBALS['__test_rest_responses'] = [];
$GLOBALS['__test_rest_requests'] = [];
$GLOBALS['__test_settings_errors'] = [];
$GLOBALS['__test_transients'] = [];

// The Events Calendar plugin stubs: makes that event source "active" in tests.
if (!class_exists('Tribe__Events__Main')) {
    class Tribe__Events__Main {
    }
}

if (!class_exists('Tribe__Events__REST__V1__System')) {
    class Tribe__Events__REST__V1__System {
        public function tec_rest_api_is_enabled(): bool {
            return true;
        }
    }
}

class WP_Term {
    public $term_id = 0;
    public $name = '';
    public $slug = '';
    public $taxonomy = '';
    public function __construct(array $props = []) {
        foreach ($props as $key => $value) {
            $this->$key = $value;
        }
    }
}

class WP_REST_Request {
    private $method;
    private $route;
    private $params = [];
    public function __construct($method = 'GET', $route = '') {
        $this->method = $method;
        $this->route = $route;
    }
    public function set_param($key, $value) {
        $this->params[$key] = $value;
    }
    public function get_param($key) {
        return $this->params[$key] ?? null;
    }
    public function get_params() {
        return $this->params;
    }
    public function get_route() {
        return $this->route;
    }
    public function get_method() {
        return $this->method;
    }
}

class WP_REST_Response {
    private $data;
    private $status;
    private $headers = [];
    public function __construct($data = null, $status = 200) {
        $this->data = $data;
        $this->status = $status;
    }
    public function get_data() {
        return $this->data;
    }
    public function get_status() {
        return $this->status;
    }
    public function get_headers() {
        return $this->headers;
    }
}

if (!function_exists('add_filter')) {
    function add_filter(...$args) {
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return abs((int) $value);
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($tag, $value) {
        return $value;
    }
}

if (!function_exists('current_time')) {
    function current_time($format) {
        return (new DateTimeImmutable('now', wp_timezone()))->format($format);
    }
}

if (!function_exists('wp_timezone')) {
    function wp_timezone() {
        return new DateTimeZone('UTC');
    }
}

if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string() {
        return 'UTC';
    }
}

if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $GLOBALS['__test_options'][$key] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($key, $value, $autoload = null) {
        $GLOBALS['__test_options'][$key] = $value;
        return true;
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'Test Masjid';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.test/' . ltrim($path, '/');
    }
}

if (!function_exists('get_post')) {
    function get_post($post_id) {
        return $GLOBALS['__test_posts'][(int) $post_id] ?? null;
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id) {
        $post = get_post($post_id);
        return $post->post_title ?? '';
    }
}

if (!function_exists('get_post_field')) {
    function get_post_field($field, $post_id) {
        $post = get_post($post_id);
        return $post->{$field} ?? '';
    }
}

if (!function_exists('get_post_time')) {
    function get_post_time($format, $gmt = false, $post = null) {
        return '2026-01-01T00:00:00+00:00';
    }
}

if (!function_exists('get_the_post_thumbnail_url')) {
    function get_the_post_thumbnail_url($post_id, $size = 'thumbnail') {
        return false;
    }
}

if (!function_exists('get_the_category')) {
    function get_the_category($post_id) {
        return [];
    }
}

if (!function_exists('get_the_terms')) {
    function get_the_terms($post_id, $taxonomy) {
        return $GLOBALS['__test_terms'][$taxonomy . ':' . (int) $post_id] ?? false;
    }
}

if (!function_exists('get_term')) {
    function get_term($term_id, $taxonomy) {
        return $GLOBALS['__test_terms'][$taxonomy . ':' . (int) $term_id] ?? null;
    }
}

if (!function_exists('get_terms')) {
    function get_terms($args) {
        $taxonomy = is_array($args) ? ($args['taxonomy'] ?? '') : $args;
        return $GLOBALS['__test_terms_list'][$taxonomy] ?? [];
    }
}

if (!function_exists('get_permalink')) {
    function get_permalink($post_id) {
        return home_url('/?p=' . (int) $post_id);
    }
}

if (!function_exists('rest_url')) {
    function rest_url($path = '') {
        return home_url('/wp-json/' . ltrim($path, '/'));
    }
}

if (!function_exists('rest_do_request')) {
    function rest_do_request($request) {
        $GLOBALS['__test_rest_requests'][] = $request;
        if (!empty($GLOBALS['__test_rest_responses'])) {
            return array_shift($GLOBALS['__test_rest_responses']);
        }
        return new WP_REST_Response([], 200);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        return trim(strip_tags((string) $value));
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($value) {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower(strip_tags((string) $value)));
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($value) {
        return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }
}

if (!function_exists('sanitize_url')) {
    function sanitize_url($value) {
        return esc_url_raw((string) $value);
    }
}

if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color($value) {
        return preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $value) ? $value : '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return $value;
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action) {
        return 1;
    }
}

if (!function_exists('add_settings_error')) {
    function add_settings_error($setting, $code, $message = '', $type = 'error') {
        $GLOBALS['__test_settings_errors'][] = compact('setting', 'code', 'message', 'type');
    }
}

if (!function_exists('register_setting')) {
    function register_setting(...$args) {
    }
}

if (!function_exists('wp_generate_uuid4')) {
    function wp_generate_uuid4() {
        return '00000000-0000-4000-8000-000000000000';
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return esc_url_raw((string) $url);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo esc_html(__($text, $domain));
    }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') {
        echo esc_attr(__($text, $domain));
    }
}

if (!function_exists('esc_html_x')) {
    function esc_html_x($text, $context, $domain = 'default') {
        return esc_html($text);
    }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $display = true) {
        return __checked_selected_helper($checked, $current, $display, 'checked');
    }
}

if (!function_exists('selected')) {
    function selected($selected, $current = true, $display = true) {
        return __checked_selected_helper($selected, $current, $display, 'selected');
    }
}

if (!function_exists('disabled')) {
    function disabled($disabled, $current = true, $display = true) {
        return __checked_selected_helper($disabled, $current, $display, 'disabled');
    }
}

if (!function_exists('__checked_selected_helper')) {
    function __checked_selected_helper($helper, $current, $display, $attribute) {
        $result = (string) $helper === (string) $current ? " {$attribute}='{$attribute}'" : '';
        if ($display) {
            echo $result;
        }
        return $result;
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($group) {
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null) {
    }
}

if (!function_exists('wp_get_attachment_url')) {
    function wp_get_attachment_url($attachment_id) {
        return (int) $attachment_id ? home_url('/wp-content/uploads/' . (int) $attachment_id . '.png') : false;
    }
}

if (!function_exists('add_options_page')) {
    function add_options_page(...$args) {
    }
}

if (!function_exists('admin_url')) {
    function admin_url($path = '') {
        return home_url('/wp-admin/' . ltrim($path, '/'));
    }
}

if (!function_exists('add_query_arg')) {
    function add_query_arg(...$args) {
        $url = is_array($args[0]) || func_num_args() >= 3 ? array_pop($args) : $args[2];
        return $url;
    }
}

if (!function_exists('wp_nonce_url')) {
    function wp_nonce_url($url, $action, $name = '_wpnonce') {
        return $url;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

$plugin_dir = dirname(__DIR__) . '/wp-content/plugins/masjidfeed-app';

require_once $plugin_dir . '/vendor-prefixed/autoload.php';
require_once $plugin_dir . '/includes/class-firebase-exceptions.php';
require_once $plugin_dir . '/includes/class-firebase-client.php';
require_once $plugin_dir . '/includes/class-firebase.php';
require_once $plugin_dir . '/includes/class-push-notifications.php';
require_once $plugin_dir . '/includes/class-settings.php';
require_once $plugin_dir . '/includes/class-event-sources.php';
require_once $plugin_dir . '/includes/class-event-source-awesome-calendar-events.php';
require_once $plugin_dir . '/includes/class-event-source-the-events-calendar.php';
require_once $plugin_dir . '/includes/class-rest-api.php';
