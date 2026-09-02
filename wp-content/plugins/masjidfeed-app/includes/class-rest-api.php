<?php
/**
 * REST API endpoints for the Masjid App mobile client.
 *
 * Namespace: masjid/v1
 *  - GET /config         App configuration (branding, contact, feature flags)
 *  - GET /salahapi       SalahAPI prayer time document (proxied from Muslim Prayer Times)
 *  - GET /events         Upcoming events
 *  - GET /announcements  Announcements (regular WordPress posts)
 *  - GET /posts/{id}     A published post with optional event fields
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_REST_API {

    const NAMESPACE_ = 'masjid/v1';
    const LIST_LIMIT = 20;
    const MAX_LIMIT = 100;
    const CLIENT_CACHE_TTL = 60;
    const SERVER_CACHE_TTL = DAY_IN_SECONDS;
    const CONFIG_CACHE_KEY = 'masjidfeed_config_response_v6';
    const CONFIG_SHARED_CACHE_TTL = HOUR_IN_SECONDS;
    const EVENTS_CACHE_KEY = 'masjidfeed_events_response_v9';
    const EVENTS_SHARED_CACHE_TTL = 5 * MINUTE_IN_SECONDS;
    const ANNOUNCEMENTS_CACHE_KEY = 'masjidfeed_announcements_response_v3';
    const FRIDAY_ANNOUNCEMENTS_CACHE_KEY = 'masjidfeed_announcements_response_v8_friday';
    const REGULAR_ANNOUNCEMENTS_CACHE_KEY = 'masjidfeed_announcements_response_v8_regular';
    const ANNOUNCEMENTS_SHARED_CACHE_TTL = 10 * MINUTE_IN_SECONDS;
    const POST_SHARED_CACHE_TTL = HOUR_IN_SECONDS;

    private $authorized_firebase;

    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('save_post_post', array($this, 'invalidate_post_caches'));
        add_action('deleted_post', array($this, 'invalidate_post_caches'));
        add_action('added_post_meta', array($this, 'invalidate_post_meta_caches'), 10, 4);
        add_action('updated_post_meta', array($this, 'invalidate_post_meta_caches'), 10, 4);
        add_action('deleted_post_meta', array($this, 'invalidate_post_meta_caches'), 10, 4);
        add_action('set_object_terms', array($this, 'invalidate_post_caches'));
        add_action('add_option_' . MASJIDFEED_OPTION_KEY, array($this, 'invalidate_settings_caches'));
        add_action('update_option_' . MASJIDFEED_OPTION_KEY, array($this, 'invalidate_settings_caches'));
        add_filter('rest_exposed_cors_headers', array($this, 'expose_cache_headers'), 10, 2);
        add_filter('rest_allowed_cors_headers', array($this, 'allow_conditional_request_headers'), 10, 2);
        add_filter('rest_request_before_callbacks', array($this, 'validate_client_user_agent'), 5, 3);
    }

    /**
     * Require client identification in the User-Agent header.
     */
    public function validate_client_user_agent($response, $handler, $request) {
        if (null !== $response || !$this->is_masjid_feed_request($request) || 'OPTIONS' === $request->get_method()) {
            return $response;
        }

        if (null === self::parse_client_user_agent($request->get_header('user-agent'))) {
            return new WP_Error(
                'masjidfeed_invalid_user_agent',
                __('User-Agent must use appname/version format.', 'masjidfeed-app'),
                array('status' => 400)
            );
        }

        return $response;
    }

    /**
     * Parse an appname/version User-Agent value.
     */
    public static function parse_client_user_agent($value) {
        $value = trim((string) $value);
        if (strlen($value) > 201 || !preg_match('/^([A-Za-z0-9][A-Za-z0-9._-]{0,99})\/([A-Za-z0-9][A-Za-z0-9._+-]{0,99})$/D', $value, $matches)) {
            return null;
        }

        return array(
            'name' => $matches[1],
            'version' => $matches[2],
        );
    }

    public function register_routes() {
        register_rest_route(self::NAMESPACE_, '/config', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_config'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE_, '/salahapi', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_salahapi'),
            'permission_callback' => '__return_true',
        ));

        register_rest_route(self::NAMESPACE_, '/events', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_events'),
            'permission_callback' => '__return_true',
            'args' => $this->get_list_route_args(),
        ));

        register_rest_route(self::NAMESPACE_, '/announcements', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_announcements'),
            'permission_callback' => '__return_true',
            'args' => $this->get_list_route_args(),
        ));

        $post_route = array(
            'methods' => 'GET',
            'callback' => array($this, 'get_post_item'),
            'permission_callback' => '__return_true',
            'args' => array(
                'id' => array(
                    'sanitize_callback' => 'absint',
                ),
            ),
        );
        register_rest_route(self::NAMESPACE_, '/posts/(?P<id>\d+)', $post_route);

        register_rest_route(self::NAMESPACE_, '/push/registrations', array(
            array(
                'methods' => 'POST',
                'callback' => array($this, 'register_push_token'),
                'permission_callback' => array($this, 'authorize_push_registration'),
                'args' => $this->get_push_registration_args(true),
            ),
            array(
                'methods' => 'DELETE',
                'callback' => array($this, 'unregister_push_token'),
                'permission_callback' => array($this, 'authorize_push_registration'),
                'args' => $this->get_push_registration_args(false),
            ),
        ));
    }

    /**
     * GET /config
     */
    public function get_config($request) {
        $cached_response = $this->get_cached_response($request, self::CONFIG_CACHE_KEY, self::CONFIG_SHARED_CACHE_TTL);
        if ($cached_response) {
            return $cached_response;
        }

        $opts = Masjid_Feed_Settings::get_all_options();
        $donation_url = $opts['donation_url'];
        $ramadan_url = $opts['ramadan_url'];
        if ($opts['content_only_rendering_enabled']) {
            $donation_url = $this->get_content_only_url($donation_url);
            $ramadan_url = $this->get_content_only_url($ramadan_url);
        }

        $response = array(
            'masjid' => array(
                'id' => $opts['masjid_id'],
                'name' => $opts['masjid_name'],
                'timezone' => $opts['timezone'],
            ),
            'branding' => array(
                'logoUrl' => $opts['logo_id'] ? wp_get_attachment_url($opts['logo_id']) : '',
                'splashLogoUrl' => $opts['splash_id'] ? wp_get_attachment_url($opts['splash_id']) : '',
                'primaryColor' => $opts['primary_color'],
                'primaryColorDark' => $opts['primary_color_dark'],
            ),
            'endpoints' => array(
                'salahApiUrl' => rest_url(self::NAMESPACE_ . '/salahapi'),
                'eventsUrl' => rest_url(self::NAMESPACE_ . '/events'),
                'announcementsUrl' => rest_url(self::NAMESPACE_ . '/announcements'),
            ),
            'contact' => array(
                'address' => $opts['address'],
                'phone' => $opts['phone'],
                'email' => $opts['email'],
                'website' => $opts['website'],
            ),
            'donationUrl' => $donation_url,
            'ramadanUrl' => $ramadan_url,
            'social' => array(
                'facebook' => $opts['facebook'],
                'instagram' => $opts['instagram'],
                'whatsapp' => $opts['whatsapp'],
            ),
            'featureFlags' => array(
                'events' => (bool) $opts['feature_events'] && null !== Masjid_Feed_Event_Sources::get_source($opts['events_source']),
                'announcements' => (bool) $opts['feature_announcements'],
                'donations' => (bool) $opts['feature_donations'],
                'qibla' => (bool) $opts['feature_qibla'],
                'prayerReminders' => (bool) $opts['feature_prayer_reminders'],
            ),
            'pushNotifications' => array(
                'enabled' => (bool) $opts['push_enabled'],
                'configured' => Masjid_Feed_Credential_Store::has_credentials()
                    && !empty($opts['firebase_ios_config'])
                    && !empty($opts['firebase_android_config']),
                'registrationUrl' => rest_url(self::NAMESPACE_ . '/push/registrations'),
                'firebase' => Masjid_Feed_Settings::get_public_firebase_config(),
            ),
        );

        return $this->cache_response($request, self::CONFIG_CACHE_KEY, $response, self::CONFIG_SHARED_CACHE_TTL);
    }

    /**
     * GET /salahapi
     *
     * Proxies the Muslim Prayer Times plugin's SalahAPI document so masjidfeed-app
     * does not duplicate prayer time calculation logic.
     */
    public function get_salahapi($request) {
        if (!function_exists('muslprti_salah_api_endpoint')) {
            return new WP_Error(
                'masjidfeed_missing_dependency',
                __('The Muslim Prayer Times plugin is required to provide prayer time data.', 'masjidfeed-app'),
                array('status' => 503)
            );
        }

        return muslprti_salah_api_endpoint($request);
    }

    /**
     * Shared query args for list endpoints.
     */
    private function get_list_route_args() {
        return array(
            'limit' => array(
                'type' => 'integer',
                'required' => false,
                'default' => self::LIST_LIMIT,
                'minimum' => 1,
                'maximum' => self::MAX_LIMIT,
                'sanitize_callback' => function($value) {
                    return self::resolve_list_limit($value);
                },
            ),
        );
    }

    /**
     * Resolve the list limit: default 20, values below 1 fall back to the
     * default, values above 100 are capped at 100.
     */
    public static function resolve_list_limit($value) {
        $value = absint($value);
        if ($value < 1) {
            return self::LIST_LIMIT;
        }
        return min($value, self::MAX_LIMIT);
    }

    /**
     * GET /events
     */
    public function get_events($request) {
        $opts = Masjid_Feed_Settings::get_all_options();
        $source = Masjid_Feed_Event_Sources::get_source($opts['events_source']);
        if (!$source) {
            return new WP_Error(
                'masjidfeed_missing_dependency',
                __('A supported events plugin must be selected in MasjidFeed App settings and be active to provide event data.', 'masjidfeed-app'),
                array('status' => 503)
            );
        }

        $limit = self::resolve_list_limit($request->get_param('limit'));

        $cached_response = $this->get_cached_list_response($request, self::EVENTS_CACHE_KEY, $limit, self::EVENTS_SHARED_CACHE_TTL);
        if ($cached_response) {
            return $cached_response;
        }

        $events = $source->get_upcoming_events($opts, self::MAX_LIMIT);

        usort($events, function($first, $second) {
            return strcmp((string) $first['eventDateTime'], (string) $second['eventDateTime']);
        });

        return $this->cache_list_response($request, self::EVENTS_CACHE_KEY, $events, $limit, self::EVENTS_SHARED_CACHE_TTL);
    }

    /**
     * GET /announcements
     */
    public function get_announcements($request) {
        $opts = Masjid_Feed_Settings::get_all_options();
        $now = current_datetime();
        $use_friday_category = !empty($opts['friday_announcements_category']) && '5' === $now->format('N');
        list($client_ttl, $shared_ttl) = $this->get_announcements_cache_ttls(
            $now,
            !empty($opts['friday_announcements_category'])
        );
        $limit = self::resolve_list_limit($request->get_param('limit'));
        $etag_variant = $use_friday_category ? 'friday' : 'regular';
        $cache_key = $use_friday_category
            ? self::FRIDAY_ANNOUNCEMENTS_CACHE_KEY
            : self::REGULAR_ANNOUNCEMENTS_CACHE_KEY;

        $cached_response = $this->get_cached_list_response(
            $request,
            $cache_key,
            $limit,
            $shared_ttl,
            $etag_variant,
            $client_ttl
        );
        if ($cached_response) {
            return $cached_response;
        }

        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'has_password' => false,
            'posts_per_page' => self::MAX_LIMIT,
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $tax_query = $use_friday_category
            ? $this->build_tax_query(array($opts['friday_announcements_category']), array())
            : $this->build_tax_query($opts['announcements_categories'], $opts['announcements_tags']);
        if ($tax_query) {
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- required to apply the configured announcement filters; results are cached at the REST layer.
            $args['tax_query'] = $tax_query;
        }

        $query = new WP_Query($args);
        $announcements = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();

                $announcements[] = $this->build_base_post($post_id);
            }
            wp_reset_postdata();
        }

        return $this->cache_list_response(
            $request,
            $cache_key,
            $announcements,
            $limit,
            $shared_ttl,
            $etag_variant,
            $client_ttl
        );
    }

    /**
     * GET /posts/{id}
     */
    public function get_post_item($request) {
        $post_id = absint($request['id']);
        $post = get_post($post_id);

        if (!$post || 'publish' !== $post->post_status || '' !== $post->post_password) {
            return new WP_Error(
                'masjidfeed_post_not_found',
                __('Post not found.', 'masjidfeed-app'),
                array('status' => 404)
            );
        }

        $cache_key = $this->get_post_cache_key($post_id);
        $cached_response = $this->get_cached_response(
            $request,
            $cache_key,
            self::POST_SHARED_CACHE_TTL
        );
        if ($cached_response) {
            return $cached_response;
        }

        $opts = Masjid_Feed_Settings::get_all_options();
        $source = Masjid_Feed_Event_Sources::get_source($opts['events_source']);
        $data = $source ? $source->build_post($post_id) : null;
        if (!is_array($data)) {
            if ('post' !== $post->post_type) {
                return new WP_Error(
                    'masjidfeed_post_not_found',
                    __('Post not found.', 'masjidfeed-app'),
                    array('status' => 404)
                );
            }
            $data = $this->build_base_post($post_id);
        }

        return $this->cache_response($request, $cache_key, $data, self::POST_SHARED_CACHE_TTL);
    }

    public function register_push_token($request) {
        $token = trim((string) $request->get_param('token'));
        $previous_token = trim((string) $request->get_param('previousToken'));
        try {
            $result = $this->authorized_firebase->subscribe($token);
            if ($previous_token && !hash_equals($token, $previous_token)) {
                $this->authorized_firebase->unsubscribe($previous_token);
            }
            return new WP_REST_Response(array('registered' => true, 'result' => $this->summarize_topic_result($result)), 200);
        } catch (Throwable $exception) {
            return new WP_Error('masjidfeed_firebase_registration_failed', __('The device could not be registered for push notifications.', 'masjidfeed-app'), array('status' => 502));
        }
    }

    public function unregister_push_token($request) {
        try {
            $result = $this->authorized_firebase->unsubscribe(trim((string) $request->get_param('token')));
            return new WP_REST_Response(array('registered' => false, 'result' => $this->summarize_topic_result($result)), 200);
        } catch (Throwable $exception) {
            return new WP_Error('masjidfeed_firebase_unregistration_failed', __('The device could not be unregistered from push notifications.', 'masjidfeed-app'), array('status' => 502));
        }
    }

    public function authorize_push_registration($request) {
        if (!Masjid_Feed_Settings::get_option('push_enabled')) {
            return new WP_Error('masjidfeed_push_disabled', __('Push notifications are disabled.', 'masjidfeed-app'), array('status' => 503));
        }
        if (!hash_equals((string) Masjid_Feed_Settings::get_option('masjid_id'), (string) $request->get_param('masjidId'))) {
            return new WP_Error('masjidfeed_tenant_mismatch', __('The requested tenant does not match this site.', 'masjidfeed-app'), array('status' => 400));
        }
        if (!$this->consume_registration_rate_limit()) {
            return new WP_Error('masjidfeed_rate_limited', __('Too many push registration attempts. Try again later.', 'masjidfeed-app'), array('status' => 429));
        }

        $app_check_token = trim((string) $request->get_header('x-firebase-appcheck'));
        if ('' === $app_check_token) {
            return new WP_Error('masjidfeed_app_check_required', __('A Firebase App Check token is required.', 'masjidfeed-app'), array('status' => 401));
        }
        $firebase_config = Masjid_Feed_Settings::get_public_firebase_config();
        $platform = (string) $request->get_param('platform');
        $platform_app_id = $firebase_config[$platform]['appId'] ?? '';
        if ('' === $platform_app_id) {
            return new WP_Error('masjidfeed_firebase_not_configured', __('Firebase mobile application configuration is incomplete.', 'masjidfeed-app'), array('status' => 503));
        }
        try {
            $this->authorized_firebase = new Masjid_Feed_Firebase();
            $this->authorized_firebase->verify_app_check($app_check_token, array($platform_app_id));
        } catch (Throwable $exception) {
            return new WP_Error('masjidfeed_app_check_invalid', __('The Firebase App Check token is invalid.', 'masjidfeed-app'), array('status' => 401));
        }
        return true;
    }

    /**
     * Invalidate cached list payloads after relevant content or settings changes.
     */
    public function invalidate_list_caches() {
        delete_transient(self::EVENTS_CACHE_KEY);
        delete_transient(self::ANNOUNCEMENTS_CACHE_KEY);
        delete_transient(self::FRIDAY_ANNOUNCEMENTS_CACHE_KEY);
        delete_transient(self::REGULAR_ANNOUNCEMENTS_CACHE_KEY);
    }

    /**
     * Invalidate all payloads affected by a settings change.
     */
    public function invalidate_settings_caches() {
        delete_transient(self::CONFIG_CACHE_KEY);
        $this->invalidate_list_caches();
    }

    /**
     * Invalidate list and post payloads affected by a post change.
     */
    public function invalidate_post_caches($post_id) {
        $this->invalidate_list_caches();
        delete_transient($this->get_post_cache_key(absint($post_id)));
    }

    /**
     * Invalidate caches after a post metadata change.
     */
    public function invalidate_post_meta_caches($meta_id, $post_id) {
        $this->invalidate_post_caches($post_id);
    }

    /**
     * Expose ETag to cross-origin clients for Masjid App routes.
     */
    public function expose_cache_headers($headers, $request) {
        if ($this->is_masjid_feed_request($request)) {
            $headers[] = 'ETag';
        }

        return array_values(array_unique($headers));
    }

    /**
     * Allow cross-origin clients to send conditional request headers.
     */
    public function allow_conditional_request_headers($headers, $request) {
        if ($this->is_masjid_feed_request($request)) {
            $headers[] = 'If-None-Match';
            $headers[] = 'X-Firebase-AppCheck';
        }

        return array_values(array_unique($headers));
    }

    /**
     * Build a WP_Query tax_query from selected category/tag term IDs.
     * Categories and tags are combined with OR relation; empty selections are skipped.
     */
    private function build_tax_query($category_ids, $tag_ids) {
        $clauses = array();

        if (!empty($category_ids)) {
            $clauses[] = array(
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => array_map('absint', $category_ids),
            );
        }

        if (!empty($tag_ids)) {
            $clauses[] = array(
                'taxonomy' => 'post_tag',
                'field' => 'term_id',
                'terms' => array_map('absint', $tag_ids),
            );
        }

        if (empty($clauses)) {
            return array();
        }

        if (count($clauses) === 1) {
            return $clauses;
        }

        $clauses['relation'] = 'OR';
        return $clauses;
    }

    /**
     * Return a cached payload with HTTP cache headers when available.
     */
    private function get_cached_response($request, $cache_key, $shared_ttl, $client_ttl = self::CLIENT_CACHE_TTL) {
        $cached = get_transient($cache_key);
        if (!is_array($cached) || !isset($cached['data'], $cached['etag'])) {
            return null;
        }

        return $this->build_cacheable_response($request, $cached['data'], $cached['etag'], $shared_ttl, $client_ttl);
    }

    /**
     * Cache a payload and return it with HTTP cache headers.
     */
    private function cache_response($request, $cache_key, $data, $shared_ttl, $etag_variant = '', $client_ttl = self::CLIENT_CACHE_TTL) {
        $etag = $this->compute_etag($data, $etag_variant);

        set_transient($cache_key, array(
            'data' => $data,
            'etag' => $etag,
        ), self::SERVER_CACHE_TTL);

        return $this->build_cacheable_response($request, $data, $etag, $shared_ttl, $client_ttl);
    }

    /**
     * Return a cached list payload sliced to the requested limit. The shared
     * cache always holds the full payload, so every limit value is served
     * from the same cache entry with its own ETag.
     */
    private function get_cached_list_response($request, $cache_key, $limit, $shared_ttl, $etag_variant = '', $client_ttl = self::CLIENT_CACHE_TTL) {
        $cached = get_transient($cache_key);
        if (!is_array($cached) || !isset($cached['data']) || !is_array($cached['data'])) {
            return null;
        }

        $data = array_slice($cached['data'], 0, $limit);
        return $this->build_cacheable_response($request, $data, $this->compute_etag($data, $etag_variant), $shared_ttl, $client_ttl);
    }

    /**
     * Cache a full list payload and return it sliced to the requested limit.
     */
    private function cache_list_response($request, $cache_key, $data, $limit, $shared_ttl, $etag_variant = '', $client_ttl = self::CLIENT_CACHE_TTL) {
        set_transient($cache_key, array('data' => $data), self::SERVER_CACHE_TTL);

        $data = array_slice($data, 0, $limit);
        return $this->build_cacheable_response($request, $data, $this->compute_etag($data, $etag_variant), $shared_ttl, $client_ttl);
    }

    /**
     * Compute the weak ETag for a payload.
     */
    private function compute_etag($data, $variant = '') {
        return 'W/"' . hash('sha256', $variant . wp_json_encode($data)) . '"';
    }

    /**
     * Build a cacheable REST response and honor If-None-Match.
     */
    private function build_cacheable_response($request, $data, $etag, $shared_ttl, $client_ttl = self::CLIENT_CACHE_TTL) {
        $status = $this->if_none_match_matches($request->get_header('if-none-match'), $etag) ? 304 : 200;
        $response = new WP_REST_Response(304 === $status ? null : $data, $status);

        $response->header('ETag', $etag);
        $response->header(
            'Cache-Control',
            sprintf('public, max-age=%d, s-maxage=%d', $client_ttl, $shared_ttl)
        );

        return $response;
    }

    /**
     * Keep HTTP caches from spanning a Friday-category mode transition.
     */
    private function get_announcements_cache_ttls($now, $has_friday_category) {
        if (!$has_friday_category) {
            return array(self::CLIENT_CACHE_TTL, self::ANNOUNCEMENTS_SHARED_CACHE_TTL);
        }

        $day_of_week = (int) $now->format('N');
        $days_until_transition = 5 === $day_of_week ? 1 : (5 - $day_of_week + 7) % 7;
        $next_transition = (clone $now)->setTime(0, 0)->modify('+' . $days_until_transition . ' days');
        $seconds_until_transition = max(0, $next_transition->getTimestamp() - $now->getTimestamp());

        return array(
            min(self::CLIENT_CACHE_TTL, $seconds_until_transition),
            min(self::ANNOUNCEMENTS_SHARED_CACHE_TTL, $seconds_until_transition),
        );
    }

    /**
     * Check whether an If-None-Match header contains the current ETag.
     */
    private function if_none_match_matches($if_none_match, $etag) {
        if (!is_string($if_none_match) || '' === trim($if_none_match)) {
            return false;
        }

        foreach (explode(',', $if_none_match) as $candidate) {
            $candidate = trim($candidate);

            if ('*' === $candidate) {
                return true;
            }

            if (0 === strpos($candidate, 'W/')) {
                $candidate = substr($candidate, 2);
            }

            $current_etag = 0 === strpos($etag, 'W/') ? substr($etag, 2) : $etag;
            if ($current_etag === $candidate) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check whether a request targets this plugin's REST namespace.
     */
    private function is_masjid_feed_request($request) {
        return 0 === strpos($request->get_route(), '/' . self::NAMESPACE_ . '/');
    }

    private function get_push_registration_args($include_previous_token) {
        $args = array(
            'masjidId' => array('required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
            'platform' => array('required' => true, 'type' => 'string', 'enum' => array('ios', 'android')),
            'token' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => function($value) { return trim((string) $value); },
                'validate_callback' => function($value) { return is_string($value) && strlen($value) >= 20 && strlen($value) <= 4096; },
            ),
        );
        if ($include_previous_token) {
            $args['previousToken'] = array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => function($value) { return trim((string) $value); },
                'validate_callback' => function($value) { return '' === $value || (is_string($value) && strlen($value) >= 20 && strlen($value) <= 4096); },
                'default' => '',
            );
        }
        return $args;
    }

    private function consume_registration_rate_limit() {
        $address = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $key = 'masjidfeed_push_rate_' . hash('sha256', $address);
        $count = (int) get_transient($key);
        if ($count >= 30) {
            return false;
        }
        set_transient($key, $count + 1, 5 * MINUTE_IN_SECONDS);
        return true;
    }

    private function summarize_topic_result($result) {
        if (!is_array($result)) {
            return array('successful' => true);
        }
        return array(
            'successful' => empty($result['errors']),
            'errorCount' => isset($result['errors']) && is_array($result['errors']) ? count($result['errors']) : 0,
        );
    }

    private function get_content_only_url($url) {
        return $url ? add_query_arg('render', 'contentOnly', $url) : '';
    }

    /**
     * Build the common post representation. Event fields, when applicable,
     * are added by the configured event source.
     */
    public static function build_base_post($post_id) {
        return array(
            'postId' => $post_id,
            'title' => get_the_title($post_id),
            'description' => apply_filters('the_content', get_post_field('post_content', $post_id)), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- applying a core WordPress filter.
            'postUrl' => self::get_post_url($post_id),
            'publishedAt' => get_post_time('c', true, $post_id),
            'image' => get_the_post_thumbnail_url($post_id, 'large') ?: null,
            'category' => self::get_primary_category_slug($post_id),
            'url' => get_permalink($post_id),
        );
    }

    /**
     * Get the cache key for a post.
     */
    private function get_post_cache_key($post_id) {
        return 'masjidfeed_post_response_v5_' . absint($post_id);
    }

    /**
     * Get the REST URL for a post.
     */
    private static function get_post_url($post_id) {
        return rest_url(self::NAMESPACE_ . '/posts/' . absint($post_id));
    }

    /**
     * Get the slug of the first non-"uncategorized" category assigned to a post.
     */
    private static function get_primary_category_slug($post_id) {
        $categories = get_the_category($post_id);
        if (empty($categories)) {
            return '';
        }
        foreach ($categories as $category) {
            if ($category->slug !== 'uncategorized') {
                return $category->slug;
            }
        }
        return $categories[0]->slug;
    }
}
