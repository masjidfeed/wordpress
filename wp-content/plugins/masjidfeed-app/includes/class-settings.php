<?php
/**
 * Settings page for MasjidFeed App
 *
 * Stores a single option (MASJIDFEED_OPTION_KEY) holding all admin-configurable
 * values used to build the /config, /events, and /announcements REST
 * responses. Defaults are pre-populated from existing WordPress site
 * settings where sensible.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Settings {

    const GENERAL_OPTION_GROUP = 'masjidfeed_general_group';
    const PUSH_OPTION_GROUP = 'masjidfeed_push_group';
    const PARKING_OPTION_GROUP = 'masjidfeed_parking_group';
    const PARKING_SCRIPT_URL = 'https://github.com/masjidfeed/wordpress/blob/main/parking-api/parking-geojson-to-config.py';
    const PAGE_SLUG = 'masjidfeed-app-settings';
    const VIEW_NONCE_ACTION = 'masjidfeed_settings_view';
    const GATEWAY_CHALLENGE_OPTION = 'masjidfeed_gateway_challenge';
    const GATEWAY_FETCH_TOKEN_OPTION = 'masjidfeed_gateway_fetch_token';
    const GATEWAY_AUDIT_TRANSIENT = 'masjidfeed_gateway_audit';
    const GATEWAY_SEND_STATUS_TRANSIENT = 'masjidfeed_gateway_send_status';
    const SETTINGS_SNAPSHOT_TRANSIENT = 'masjidfeed_settings_snapshot';
    const PUSH_MODE_DISABLED = 'disabled';
    const PUSH_MODE_MASJIDFEED = 'masjidfeed';
    const PUSH_MODE_FCM = 'fcm';
    const PUSH_MODE_BOTH = 'both';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        add_action('admin_post_masjidfeed_register_gateway', array($this, 'handle_register_gateway'));
        add_action('admin_post_masjidfeed_validate_gateway', array($this, 'handle_validate_gateway'));
        add_action('admin_post_masjidfeed_rotate_gateway_topic', array($this, 'handle_rotate_gateway_topic'));
        add_action('admin_post_masjidfeed_fetch_gateway_audit', array($this, 'handle_fetch_gateway_audit'));
        add_action('admin_post_masjidfeed_gateway_send_status', array($this, 'handle_gateway_send_status'));
        add_action('admin_post_masjidfeed_validate_firebase', array($this, 'handle_validate_firebase'));
        add_action('admin_post_masjidfeed_test_push', array($this, 'handle_test_push'));
        add_action('admin_post_masjidfeed_toggle_api_tracing', array($this, 'handle_toggle_api_tracing'));
        add_action('admin_post_masjidfeed_clear_api_traces', array($this, 'handle_clear_api_traces'));
        add_action('admin_post_masjidfeed_show_settings', array($this, 'handle_show_settings'));
        // Late priority so it still fires on the current init run: this class
        // is constructed from the plugin's own init callback.
        add_action('init', array($this, 'sync_masjid_id'), 20);
        add_action('init', array($this, 'migrate_gateway_registration'), 20);
    }

    /**
     * Get a single stored setting, falling back to the computed default.
     */
    public static function get_option($key, $default = null) {
        $settings = get_option(MASJIDFEED_OPTION_KEY, array());
        if (isset($settings[$key]) && $settings[$key] !== '') {
            return $settings[$key];
        }
        $defaults = self::get_defaults();
        return array_key_exists($key, $defaults) ? $defaults[$key] : $default;
    }

    /**
     * Get the full settings array merged with defaults for any missing keys.
     */
    public static function get_all_options() {
        $settings = get_option(MASJIDFEED_OPTION_KEY, array());
        return array_merge(self::get_defaults(), $settings);
    }

    /**
     * Effective push delivery mode. Reads the raw stored option (not the
     * defaults-merged array) so installs saved before the mode dropdown
     * existed keep their behaviour: the old checkbox only controlled custom
     * FCM delivery. Fresh installs default to the hosted MasjidFeed service.
     *
     * @param array|null $opts Raw stored settings; read from the option when null.
     */
    public static function push_mode($opts = null) {
        if (!is_array($opts)) {
            $stored = get_option(MASJIDFEED_OPTION_KEY, array());
            $opts = is_array($stored) ? $stored : array();
        }
        $mode = (string) ($opts['push_mode'] ?? '');
        if (in_array($mode, array(self::PUSH_MODE_DISABLED, self::PUSH_MODE_MASJIDFEED, self::PUSH_MODE_FCM, self::PUSH_MODE_BOTH), true)) {
            return $mode;
        }
        if (array_key_exists('push_enabled', $opts)) {
            return !empty($opts['push_enabled']) ? self::PUSH_MODE_FCM : self::PUSH_MODE_DISABLED;
        }
        return self::PUSH_MODE_MASJIDFEED;
    }

    /**
     * True when a site has registered with the push gateway and holds the
     * secret sends are authenticated with.
     */
    public static function is_gateway_registered() {
        return '' !== self::gateway_site_id() && '' !== self::gateway_secret();
    }

    /**
     * True when a gateway registration is in progress: the gateway issued a
     * site ID for the pending site but the one-time secret has not been
     * collected yet. Pressing the registration button — labelled Validate in
     * this state — retries verification with the stored fetch token
     * (POST /v1/sites/{site_id}/verify, wp-plugin-integration.md §3.3).
     */
    public static function is_gateway_pending() {
        return '' !== self::gateway_site_id() && '' === self::gateway_secret();
    }

    /**
     * The registration lives in its own option, never inside the settings
     * array: settings form saves and other whole-array writers rewrite
     * masjidfeed_settings from their own snapshot, and any such rewrite
     * would silently drop the site ID and secret. Keeping them in a
     * dedicated option makes the registration survive every settings
     * rewrite (and the stale-cache read window that once cost this site its
     * issued secret).
     */
    public static function gateway_site_id() {
        return (string) (self::gateway_registration()['site_id'] ?? '');
    }

    public static function gateway_secret() {
        return (string) (self::gateway_registration()['secret'] ?? '');
    }

    private static function gateway_registration() {
        $registration = get_option(MASJIDFEED_GATEWAY_OPTION_KEY, array());
        return is_array($registration) ? $registration : array();
    }

    /**
     * Persist a completed registration: site ID plus the one-time gateway
     * secret in the dedicated option.
     */
    public static function save_gateway_registration(array $registration) {
        update_option(MASJIDFEED_GATEWAY_OPTION_KEY, array(
            'site_id' => (string) $registration['site_id'],
            'secret' => (string) $registration['gateway_secret'],
        ), false);
    }

    /**
     * Remember a pending site so the next attempt completes onboarding
     * through verify_site(); the previous registration's secret, if any,
     * is dropped with the site ID it belonged to — the new site replaced
     * that registration, so a kept secret would pair the new site ID with
     * a credential the gateway has already invalidated.
     */
    public static function store_gateway_pending_site(string $site_id) {
        if ('' === $site_id) {
            delete_option(MASJIDFEED_GATEWAY_OPTION_KEY);
            return;
        }
        update_option(MASJIDFEED_GATEWAY_OPTION_KEY, array('site_id' => $site_id), false);
    }

    /**
     * One-time move of the gateway registration out of the settings array
     * (where whole-array rewrites could drop it) into the dedicated option.
     */
    public function migrate_gateway_registration() {
        if ('' !== self::gateway_site_id()) {
            return;
        }
        $settings = get_option(MASJIDFEED_OPTION_KEY, null);
        if (!is_array($settings)) {
            return;
        }
        $site_id = (string) ($settings['gateway_site_id'] ?? '');
        if ('' === $site_id) {
            return;
        }
        self::save_gateway_registration(array(
            'site_id' => $site_id,
            'gateway_secret' => (string) ($settings['gateway_secret'] ?? ''),
        ));
        unset($settings['gateway_site_id'], $settings['gateway_secret']);
        update_option(MASJIDFEED_OPTION_KEY, $settings);
    }

    /**
     * Compute default values sourced from existing WordPress site settings.
     */
    public static function get_defaults() {
        return array(
            'masjid_id' => self::generate_masjid_id(),
            'masjid_name' => get_bloginfo('name'),
            'timezone' => wp_timezone_string(),
            'logo_id' => 0,
            'splash_id' => 0,
            'primary_color' => '#1B7F5C',
            'primary_color_dark' => '#3FBF8F',
            'address' => '',
            'phone' => '',
            'email' => get_option('admin_email'),
            'website' => home_url('/'),
            'donation_url' => '',
            'ramadan_url' => '',
            'facebook' => '',
            'instagram' => '',
            'whatsapp' => '',
            'feature_events' => 1,
            'feature_announcements' => 1,
            'feature_donations' => 1,
            'feature_qibla' => 1,
            'feature_prayer_reminders' => 1,
            'content_only_rendering_enabled' => 0,
            'events_source' => 'awesome_calendar_events',
            'events_categories' => array(),
            'events_tags' => array(),
            'announcements_categories' => array(),
            'announcements_tags' => array(),
            'friday_announcements_category' => 0,
            'push_enabled' => 0,
            'push_mode' => self::PUSH_MODE_MASJIDFEED,
            'push_tag' => 0,
            'push_deep_link_template' => '',
            'firebase_project_id' => '',
            'firebase_ios_config' => array(),
            'firebase_android_config' => array(),
        );
    }

    /**
     * Derive the Masjid ID from the site domain: the first 8 characters of
     * the SHA-256 hash of the lowercased host name (scheme and path are
     * ignored), prefixed with "masjid-". Not editable.
     */
    public static function generate_masjid_id() {
        $host = parse_url(home_url(), PHP_URL_HOST);
        if (!is_string($host) || '' === $host) {
            $host = (string) home_url();
        }
        $host = strtolower(rtrim($host, '/'));
        return 'masjid-' . substr(hash('sha256', $host), 0, 8);
    }

    /**
     * Keep the stored Masjid ID aligned with the URL-derived value, but only
     * when the stored value does not follow the expected "masjid-xxxxxxxx"
     * pattern (e.g. values saved by earlier plugin versions). Values that
     * already match the pattern are left untouched, even if they no longer
     * match this site's URL.
     */
    public function sync_masjid_id() {
        $settings = get_option(MASJIDFEED_OPTION_KEY, null);
        if (!is_array($settings)) {
            return;
        }
        $stored = (string) ($settings['masjid_id'] ?? '');
        if (preg_match('/^masjid-[0-9a-f]{8}$/', $stored)) {
            return;
        }
        $settings['masjid_id'] = self::generate_masjid_id();
        update_option(MASJIDFEED_OPTION_KEY, $settings);
    }

    public function add_settings_page() {
        add_options_page(
            __('MasjidFeed App Settings', 'masjidfeed-app'),
            __('MasjidFeed App', 'masjidfeed-app'),
            'manage_options',
            self::PAGE_SLUG,
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        $args = array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_settings'),
            'default' => array(),
        );
        register_setting(self::GENERAL_OPTION_GROUP, MASJIDFEED_OPTION_KEY, $args);
        register_setting(self::PUSH_OPTION_GROUP, MASJIDFEED_OPTION_KEY, $args);
        register_setting(self::PARKING_OPTION_GROUP, Masjid_Feed_Parking_Config::OPTION_KEY, array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_parking_config'),
            'default' => '',
        ));
    }

    public function enqueue_media_scripts($hook) {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('media-editor', $this->media_picker_js());
        wp_add_inline_script('media-editor', $this->events_source_js());
        wp_add_inline_script('media-editor', $this->push_mode_js());
        wp_add_inline_script('wp-color-picker', $this->color_picker_js());
    }

    private function push_mode_js() {
        return "jQuery(function(\$) {\n"
            . "    var mode = \$('#masjidfeed_push_mode');\n"
            . "    var section = \$('#masjidfeed_fcm_setup');\n"
            . "    function sync() {\n"
            . "        var value = mode.val();\n"
            . "        section.prop('open', 'fcm' === value || 'both' === value);\n"
            . "    }\n"
            . "    mode.on('change', sync);\n"
            . "    sync();\n"
            . "});";
    }

    private function events_source_js() {
        $flag_id = 'masjidfeed_flag_feature_events';
        return "jQuery(function(\$) {\n"
            . "    var source = \$('#masjidfeed_events_source');\n"
            . "    var flag = \$('#" . $flag_id . "');\n"
            . "    function sync() {\n"
            . "        var value = source.val();\n"
            . "        var none = '' === value;\n"
            . "        var isTec = 'the_events_calendar' === value;\n"
            . "        flag.prop('disabled', none);\n"
            . "        if (none) {\n"
            . "            flag.prop('checked', false);\n"
            . "        }\n"
            . "        \$('[data-for-default]').toggle(!isTec).find('input[type=checkbox]').prop('disabled', isTec);\n"
            . "        \$('[data-for-tec]').toggle(isTec).find('input[type=checkbox]').prop('disabled', !isTec);\n"
            . "    }\n"
            . "    source.on('change', sync);\n"
            . "    sync();\n"
            . "});";
    }

    private function color_picker_js() {
        return "jQuery(function(\$) {\n"
            . "    \$('.masjidapp-color-picker').wpColorPicker();\n"
            . "});";
    }

    private function media_picker_js() {
        return "jQuery(function(\$) {\n"
            . "    \$('.masjidapp-media-picker').on('click', function(e) {\n"
            . "        e.preventDefault();\n"
            . "        var button = \$(this);\n"
            . "        var targetInput = \$('#' + button.data('target'));\n"
            . "        var previewImg = \$('#' + button.data('preview'));\n"
            . "        var frame = wp.media({\n"
            . "            title: 'Select Image',\n"
            . "            button: { text: 'Use this image' },\n"
            . "            multiple: false\n"
            . "        });\n"
            . "        frame.on('select', function() {\n"
            . "            var attachment = frame.state().get('selection').first().toJSON();\n"
            . "            targetInput.val(attachment.id);\n"
            . "            previewImg.attr('src', attachment.url).show();\n"
            . "        });\n"
            . "        frame.open();\n"
            . "    });\n"
            . "});";
    }

    /**
     * Sanitize the settings array on save. Only the fields belonging to the
     * submitted tab are sanitized; fields of the other tab keep their stored
     * values so each tab can be saved independently.
     */
    public function sanitize_settings($input) {
        $input = is_array($input) ? $input : array();
        $existing = get_option(MASJIDFEED_OPTION_KEY, array());
        $existing = is_array($existing) ? $existing : array();
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, self::GENERAL_OPTION_GROUP . '-options') && !wp_verify_nonce($nonce, self::PUSH_OPTION_GROUP . '-options')) {
            return $existing;
        }
        $tab = isset($input['_tab']) ? sanitize_key(wp_unslash($input['_tab'])) : '';

        if ('push' === $tab) {
            return $this->sanitize_push_settings($input, $existing);
        }
        if ('general' === $tab) {
            return $this->sanitize_general_settings($input, $existing);
        }
        return $existing;
    }

    private function sanitize_general_settings($input, $existing) {
        $out = $existing;

        $out['masjid_id'] = self::generate_masjid_id();
        $out['masjid_name'] = sanitize_text_field($input['masjid_name'] ?? '');
        $out['timezone'] = sanitize_text_field($input['timezone'] ?? '');
        $out['logo_id'] = absint($input['logo_id'] ?? 0);
        $out['splash_id'] = absint($input['splash_id'] ?? 0);
        $out['primary_color'] = sanitize_hex_color($input['primary_color'] ?? '') ?: '';
        $out['primary_color_dark'] = sanitize_hex_color($input['primary_color_dark'] ?? '') ?: '';
        $out['address'] = sanitize_text_field($input['address'] ?? '');
        $out['phone'] = sanitize_text_field($input['phone'] ?? '');
        $out['email'] = sanitize_email($input['email'] ?? '');
        $out['website'] = sanitize_url($input['website'] ?? '');
        $out['donation_url'] = sanitize_url($input['donation_url'] ?? '');
        $out['ramadan_url'] = sanitize_url($input['ramadan_url'] ?? '');
        $out['facebook'] = sanitize_url($input['facebook'] ?? '');
        $out['instagram'] = sanitize_url($input['instagram'] ?? '');
        $out['whatsapp'] = sanitize_url($input['whatsapp'] ?? '');

        $out['events_source'] = $this->sanitize_events_source($input);

        foreach (array('feature_events', 'feature_announcements', 'feature_donations', 'feature_qibla', 'feature_prayer_reminders') as $flag) {
            $out[$flag] = !empty($input[$flag]) ? 1 : 0;
        }
        $out['content_only_rendering_enabled'] = !empty($input['content_only_rendering_enabled']) ? 1 : 0;

        if ('' === $out['events_source']) {
            $out['feature_events'] = 0;
        }

        foreach (array('events_categories', 'events_tags', 'announcements_categories', 'announcements_tags') as $tax_field) {
            $out[$tax_field] = isset($input[$tax_field]) && is_array($input[$tax_field])
                ? array_map('absint', $input[$tax_field])
                : array();
        }

        $out['friday_announcements_category'] = absint($input['friday_announcements_category'] ?? 0);

        return $out;
    }

    private function sanitize_events_source($input) {
        $key = sanitize_key(wp_unslash($input['events_source'] ?? ''));
        if ('' === $key) {
            return '';
        }
        $choices = Masjid_Feed_Event_Sources::get_choices();
        if (!isset($choices[$key])) {
            add_settings_error(MASJIDFEED_OPTION_KEY, 'invalid_events_source', __('The selected events plugin is not supported.', 'masjidfeed-app'));
            return '';
        }
        if (!$choices[$key]['available']) {
            add_settings_error(MASJIDFEED_OPTION_KEY, 'unavailable_events_source', __('The selected events plugin is not active on this site.', 'masjidfeed-app'));
            return '';
        }
        return $key;
    }

    private function sanitize_push_settings($input, $existing) {
        $out = $existing;

        $mode = sanitize_key(wp_unslash($input['push_mode'] ?? ''));
        if (!in_array($mode, array(self::PUSH_MODE_DISABLED, self::PUSH_MODE_MASJIDFEED, self::PUSH_MODE_FCM, self::PUSH_MODE_BOTH), true)) {
            $mode = self::push_mode($existing);
        }
        $out['push_mode'] = $mode;
        // Legacy consumers (queue gating, /config, device registration) keep
        // reading push_enabled, so derive it from the selected mode.
        $out['push_enabled'] = self::PUSH_MODE_DISABLED === $mode ? 0 : 1;
        $out['push_tag'] = absint($input['push_tag'] ?? 0);
        $out['push_deep_link_template'] = $this->sanitize_deep_link_template(
            $input['push_deep_link_template'] ?? '',
            $existing['push_deep_link_template'] ?? ''
        );
        $out['firebase_project_id'] = sanitize_text_field($existing['firebase_project_id'] ?? '');
        $out['firebase_ios_config'] = is_array($existing['firebase_ios_config'] ?? null) ? $existing['firebase_ios_config'] : array();
        $out['firebase_android_config'] = is_array($existing['firebase_android_config'] ?? null) ? $existing['firebase_android_config'] : array();
        $out = $this->process_firebase_uploads($out, $input);

        return $out;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $current_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        if (!in_array($current_tab, array('general', 'push', 'parking', 'debug'), true)
            || !wp_verify_nonce($nonce, self::VIEW_NONCE_ACTION)) {
            $current_tab = 'general';
        }

        $opts = self::get_all_options();
        $categories = get_categories(array('hide_empty' => false));
        $tags = get_tags(array('hide_empty' => false));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('MasjidFeed App Settings', 'masjidfeed-app'); ?></h1>
            <?php $this->render_notices(); ?>
            <nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e('MasjidFeed App settings tabs', 'masjidfeed-app'); ?>">
                <?php
                $tabs = array(
                    'general' => __('General', 'masjidfeed-app'),
                    'push' => __('Push', 'masjidfeed-app'),
                    'parking' => __('Parking', 'masjidfeed-app'),
                    'debug' => __('Debug', 'masjidfeed-app'),
                );
                foreach ($tabs as $tab => $label) :
                    $url = wp_nonce_url(
                        add_query_arg('tab', $tab, admin_url('options-general.php?page=' . self::PAGE_SLUG)),
                        self::VIEW_NONCE_ACTION
                    );
                    ?>
                    <a href="<?php echo esc_url($url); ?>" class="nav-tab<?php echo $tab === $current_tab ? ' nav-tab-active' : ''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
            if ('push' === $current_tab) {
                $this->render_push_tab($opts, $tags);
            } elseif ('parking' === $current_tab) {
                $this->render_parking_tab();
            } elseif ('debug' === $current_tab) {
                $this->render_settings_snapshot();
                $this->render_api_trace_tools();
                $this->render_gateway_tools();
                $this->render_firebase_tools();
                $this->render_validation_results();
                $this->render_recent_deliveries();
            } else {
                $this->render_general_tab($opts, $categories, $tags);
            }
            ?>
        </div>
        <?php
    }

    private function render_general_tab($opts, $categories, $tags) {
        $choices = Masjid_Feed_Event_Sources::get_choices();
        $events_source = self::resolve_events_source($opts);
        $is_tec_selected = Masjid_Feed_Event_Source_The_Events_Calendar::KEY === $events_source;
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::GENERAL_OPTION_GROUP); ?>
            <input type="hidden" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[_tab]" value="general" />

            <h2><?php esc_html_e('Masjid', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Masjid ID', 'masjidfeed-app'); ?></th>
                    <td>
                        <code id="masjidfeed_masjid_id"><?php echo esc_html($opts['masjid_id']); ?></code>
                        <p class="description"><?php esc_html_e('Generated automatically from the site URL and cannot be changed.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_masjid_name"><?php esc_html_e('Masjid Name', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_masjid_name" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[masjid_name]" value="<?php echo esc_attr($opts['masjid_name']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_timezone"><?php esc_html_e('Timezone', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <select id="masjidfeed_timezone" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[timezone]">
                            <?php foreach (DateTimeZone::listIdentifiers() as $tz) : ?>
                                <option value="<?php echo esc_attr($tz); ?>" <?php selected($opts['timezone'], $tz); ?>><?php echo esc_html($tz); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Branding', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_logo_id"><?php esc_html_e('Logo', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <input type="hidden" id="masjidfeed_logo_id" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[logo_id]" value="<?php echo esc_attr($opts['logo_id']); ?>" />
                        <img id="masjidfeed_logo_preview" src="<?php echo esc_url($opts['logo_id'] ? wp_get_attachment_url($opts['logo_id']) : ''); ?>" style="max-height:60px;<?php echo $opts['logo_id'] ? '' : 'display:none;'; ?>" />
                        <p><button type="button" class="button masjidapp-media-picker" data-target="masjidfeed_logo_id" data-preview="masjidfeed_logo_preview"><?php esc_html_e('Select Logo', 'masjidfeed-app'); ?></button></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_splash_id"><?php esc_html_e('Splash Logo', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <input type="hidden" id="masjidfeed_splash_id" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[splash_id]" value="<?php echo esc_attr($opts['splash_id']); ?>" />
                        <img id="masjidfeed_splash_preview" src="<?php echo esc_url($opts['splash_id'] ? wp_get_attachment_url($opts['splash_id']) : ''); ?>" style="max-height:60px;<?php echo $opts['splash_id'] ? '' : 'display:none;'; ?>" />
                        <p><button type="button" class="button masjidapp-media-picker" data-target="masjidfeed_splash_id" data-preview="masjidfeed_splash_preview"><?php esc_html_e('Select Splash Logo', 'masjidfeed-app'); ?></button></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_primary_color"><?php esc_html_e('Primary Color', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_primary_color" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[primary_color]" value="<?php echo esc_attr($opts['primary_color']); ?>" class="masjidapp-color-picker" data-default-color="#1B7F5C" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_primary_color_dark"><?php esc_html_e('Primary Color (Dark Mode)', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_primary_color_dark" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[primary_color_dark]" value="<?php echo esc_attr($opts['primary_color_dark']); ?>" class="masjidapp-color-picker" data-default-color="#3FBF8F" /></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Contact & Social', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_address"><?php esc_html_e('Address', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_address" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[address]" value="<?php echo esc_attr($opts['address']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_phone"><?php esc_html_e('Phone', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_phone" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[phone]" value="<?php echo esc_attr($opts['phone']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_email"><?php esc_html_e('Email', 'masjidfeed-app'); ?></label></th>
                    <td><input type="email" id="masjidfeed_email" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[email]" value="<?php echo esc_attr($opts['email']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_website"><?php esc_html_e('Website', 'masjidfeed-app'); ?></label></th>
                    <td><input type="url" id="masjidfeed_website" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[website]" value="<?php echo esc_attr($opts['website']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_facebook"><?php esc_html_e('Facebook URL', 'masjidfeed-app'); ?></label></th>
                    <td><input type="url" id="masjidfeed_facebook" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[facebook]" value="<?php echo esc_attr($opts['facebook']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_instagram"><?php esc_html_e('Instagram URL', 'masjidfeed-app'); ?></label></th>
                    <td><input type="url" id="masjidfeed_instagram" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[instagram]" value="<?php echo esc_attr($opts['instagram']); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_whatsapp"><?php esc_html_e('WhatsApp URL', 'masjidfeed-app'); ?></label></th>
                    <td><input type="url" id="masjidfeed_whatsapp" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[whatsapp]" value="<?php echo esc_attr($opts['whatsapp']); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Donation', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_donation_url"><?php esc_html_e('Donation Page URL', 'masjidfeed-app'); ?></label></th>
                    <td><input type="url" id="masjidfeed_donation_url" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[donation_url]" value="<?php echo esc_attr($opts['donation_url']); ?>" class="regular-text" /></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Ramadan', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_ramadan_url"><?php esc_html_e('Ramadan Page URL', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <input type="url" id="masjidfeed_ramadan_url" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[ramadan_url]" value="<?php echo esc_attr($opts['ramadan_url']); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Optional page shown by the mobile app during Ramadan.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Feature Flags', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <?php
                $flags = array(
                    'feature_events' => __('Events', 'masjidfeed-app'),
                    'feature_announcements' => __('Announcements', 'masjidfeed-app'),
                    'feature_donations' => __('Donations', 'masjidfeed-app'),
                    'feature_qibla' => __('Qibla', 'masjidfeed-app'),
                    'feature_prayer_reminders' => __('Prayer Reminders', 'masjidfeed-app'),
                );
                foreach ($flags as $key => $label) :
                    $events_flag_disabled = ('feature_events' === $key && '' === $events_source);
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="masjidfeed_flag_<?php echo esc_attr($key); ?>" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($opts[$key]) && !$events_flag_disabled); ?> <?php disabled($events_flag_disabled); ?> />
                                <?php esc_html_e('Enabled', 'masjidfeed-app'); ?>
                            </label>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <h2><?php esc_html_e('Content-only Rendering', 'masjidfeed-app'); ?></h2>
            <p class="description"><?php esc_html_e('Allow donation and other pages to render without the site header and footer.', 'masjidfeed-app'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Content-only URLs', 'masjidfeed-app'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[content_only_rendering_enabled]" value="1" <?php checked(!empty($opts['content_only_rendering_enabled'])); ?> />
                            <?php esc_html_e('Enable content-only rendering', 'masjidfeed-app'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Events', 'masjidfeed-app'); ?></h2>
            <p class="description"><?php esc_html_e('Select the plugin that provides events to the mobile app. If no plugin is selected, the Events feature is disabled.', 'masjidfeed-app'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_events_source"><?php esc_html_e('Events Plugin', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <select id="masjidfeed_events_source" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[events_source]">
                            <option value="" <?php selected('' === $events_source); ?>><?php echo esc_html('— ' . __('None', 'masjidfeed-app') . ' —'); ?></option>
                            <?php foreach ($choices as $key => $choice) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($events_source, $key); ?> <?php disabled(!$choice['available']); ?>>
                                    <?php echo esc_html($choice['label'] . (!$choice['available'] ? __(' (not installed)', 'masjidfeed-app') : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ('' === $events_source) : ?>
                            <p class="description"><?php esc_html_e('No events plugin is selected, so the Events feature flag is disabled.', 'masjidfeed-app'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Events Filter', 'masjidfeed-app'); ?></h2>
            <p class="description masjidfeed-events-filter-description" data-for-default><?php esc_html_e('Only events in the selected categories/tags will be included in the mobile app feed. Leave empty to include all events.', 'masjidfeed-app'); ?></p>
            <p class="description masjidfeed-events-filter-description" data-for-tec style="display:none;"><?php esc_html_e('Only events in the selected The Events Calendar categories will be included in the mobile app feed. Leave empty to include all events.', 'masjidfeed-app'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Categories', 'masjidfeed-app'); ?></th>
                    <td>
                        <div class="masjidfeed-events-categories" data-for-default><?php $this->render_term_checkboxes($categories, 'events_categories', $opts['events_categories'], $is_tec_selected); ?></div>
                        <div class="masjidfeed-events-categories" data-for-tec style="display:none;"><?php $this->render_term_checkboxes($this->get_events_filter_categories(Masjid_Feed_Event_Source_The_Events_Calendar::KEY, $categories), 'events_categories', $opts['events_categories'], !$is_tec_selected); ?></div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tags', 'masjidfeed-app'); ?></th>
                    <td><?php $this->render_term_checkboxes($tags, 'events_tags', $opts['events_tags']); ?></td>
                </tr>
            </table>

            <h2><?php esc_html_e('Announcements Filter', 'masjidfeed-app'); ?></h2>
            <p class="description"><?php esc_html_e('Only posts in the selected categories/tags will be included as announcements. Leave empty to include all posts.', 'masjidfeed-app'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Categories', 'masjidfeed-app'); ?></th>
                    <td><?php $this->render_term_checkboxes($categories, 'announcements_categories', $opts['announcements_categories']); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Tags', 'masjidfeed-app'); ?></th>
                    <td><?php $this->render_term_checkboxes($tags, 'announcements_tags', $opts['announcements_tags']); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_friday_announcements_category"><?php esc_html_e('Friday Category', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <select id="masjidfeed_friday_announcements_category" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[friday_announcements_category]">
                            <option value="0"><?php esc_html_e('Use regular announcement filters', 'masjidfeed-app'); ?></option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo (int) $category->term_id; ?>" <?php selected((int) $opts['friday_announcements_category'], (int) $category->term_id); ?>><?php echo esc_html($category->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('On Fridays, show only posts from this category. Friday follows the WordPress site timezone.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    private function render_push_tab($opts, $tags) {
        $mode = self::push_mode();
        $fcm_section_open = in_array($mode, array(self::PUSH_MODE_FCM, self::PUSH_MODE_BOTH), true);
        ?>
        <h2 id="push-notifications"><?php esc_html_e('Push Notifications', 'masjidfeed-app'); ?></h2>

        <h3><?php esc_html_e('MasjidFeed push service', 'masjidfeed-app'); ?></h3>
        <p class="description" style="max-width:900px;"><?php esc_html_e('Delivers notifications through the hosted MasjidFeed gateway, which holds the Firebase credentials for the MasjidApp apps. Registering verifies this site\'s domain and stores the issued secret in the settings; no Firebase project of your own is required.', 'masjidfeed-app'); ?></p>
        <?php $pending = self::is_gateway_pending(); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e('Registration', 'masjidfeed-app'); ?></th>
                <td>
                    <?php if (self::is_gateway_registered()) : ?>
                        <p><strong><?php esc_html_e('Registered.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Site ID:', 'masjidfeed-app'); ?> <code><?php echo esc_html(self::gateway_site_id()); ?></code></p>
                    <?php elseif ($pending) : ?>
                        <p><strong><?php esc_html_e('Pending.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Site ID:', 'masjidfeed-app'); ?> <code><?php echo esc_html(self::gateway_site_id()); ?></code></p>
                    <?php else : ?>
                        <p><strong><?php esc_html_e('Not registered.', 'masjidfeed-app'); ?></strong></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="masjidfeed_register_gateway" />
            <?php wp_nonce_field('masjidfeed_register_gateway'); ?>
            <p><?php submit_button($pending ? __('Validate', 'masjidfeed-app') : __('Register with MasjidFeed', 'masjidfeed-app'), 'secondary', 'submit', false); ?></p>
            <p class="description" style="max-width:900px;"><?php
                if ($pending) {
                    esc_html_e('Registration is pending: the push service could not verify this site\'s domain when it registered. Make sure the WordPress REST API is publicly reachable, then press Validate to retry — the push service re-fetches the challenge from the gateway-verification endpoint using this registration\'s fetch token (POST /v1/sites/{site_id}/verify). The pending registration expires 24 hours after it was created.', 'masjidfeed-app');
                } else {
                    esc_html_e('Registration publishes a one-time challenge at the gateway-verification REST endpoint, served only to the push service when it presents this registration’s fetch token; the service fetches it to verify this domain and issues the secret stored in the settings. If the service cannot reach this site, registration stays pending and pressing the button again retries verification.', 'masjidfeed-app');
                }
            ?></p>
        </form>
        <?php if (self::is_gateway_registered()) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="masjidfeed_rotate_gateway_topic" />
                <?php wp_nonce_field('masjidfeed_rotate_gateway_topic'); ?>
                <p><?php submit_button(__('Rotate topic keys', 'masjidfeed-app'), 'secondary', 'submit', false); ?></p>
                <p class="description" style="max-width:900px;"><?php esc_html_e('Rotating makes the push service issue a new topic for this site and stop delivering to the old one after a grace window. Use this when a topic key may have leaked: devices re-subscribe automatically the next time they fetch a topic key (keys expire after 12 hours).', 'masjidfeed-app'); ?></p>
            </form>
        <?php endif; ?>

        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields(self::PUSH_OPTION_GROUP); ?>
            <input type="hidden" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[_tab]" value="push" />

            <h3><?php esc_html_e('Delivery', 'masjidfeed-app'); ?></h3>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_push_mode"><?php esc_html_e('Push notifications', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <select id="masjidfeed_push_mode" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[push_mode]">
                            <option value="<?php echo esc_attr(self::PUSH_MODE_DISABLED); ?>" <?php selected($mode, self::PUSH_MODE_DISABLED); ?>><?php esc_html_e('Disabled', 'masjidfeed-app'); ?></option>
                            <option value="<?php echo esc_attr(self::PUSH_MODE_MASJIDFEED); ?>" <?php selected($mode, self::PUSH_MODE_MASJIDFEED); ?>><?php esc_html_e('MasjidFeed', 'masjidfeed-app'); ?></option>
                            <option value="<?php echo esc_attr(self::PUSH_MODE_FCM); ?>" <?php selected($mode, self::PUSH_MODE_FCM); ?>><?php esc_html_e('FCM', 'masjidfeed-app'); ?></option>
                            <option value="<?php echo esc_attr(self::PUSH_MODE_BOTH); ?>" <?php selected($mode, self::PUSH_MODE_BOTH); ?>><?php esc_html_e('Both', 'masjidfeed-app'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('MasjidFeed delivers through the hosted push service registered above. FCM delivers through your own Firebase project configured below. Both delivers through both channels.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_push_tag"><?php esc_html_e('Trigger tag', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <select id="masjidfeed_push_tag" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[push_tag]">
                            <option value="0"><?php esc_html_e('Select a tag', 'masjidfeed-app'); ?></option>
                            <?php foreach ($tags as $tag) : ?>
                                <option value="<?php echo (int) $tag->term_id; ?>" <?php selected((int) $opts['push_tag'], (int) $tag->term_id); ?>><?php echo esc_html($tag->name); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('A post sends once when first published with this tag. Tagged published posts can also be sent manually.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_push_deep_link_template"><?php esc_html_e('Deep-link override', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <input type="text" id="masjidfeed_push_deep_link_template" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[push_deep_link_template]" value="<?php echo esc_attr($opts['push_deep_link_template']); ?>" class="large-text code" placeholder="masjidapp://open/v1/masjids/{masjidId}/{type}/{postId}" />
                        <p class="description"><?php esc_html_e('Optional. Supports {masjidId}, {postId}, {type}, and {slug}. Must use the masjidapp:// URI scheme; leave blank for the built-in event or announcement link.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
            </table>

            <details id="masjidfeed_fcm_setup" style="max-width:900px;margin:16px 0;"<?php echo $fcm_section_open ? ' open' : ''; ?>>
                <summary><strong><?php esc_html_e('Custom Firebase (FCM) setup', 'masjidfeed-app'); ?></strong></summary>
                <div style="padding:8px 0 0 20px;">
                    <p class="description"><?php esc_html_e('Configure Firebase Cloud Messaging for this tenant. Uploaded credentials are validated and the service account is encrypted before storage.', 'masjidfeed-app'); ?></p>
                    <?php if (!Masjid_Feed_Credential_Store::is_available()) : ?>
                        <div class="notice notice-warning inline">
                            <p><strong><?php esc_html_e('Credential encryption is not configured.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Set MASJIDFEED_CREDENTIAL_KEY as described below before uploading the service-account file.', 'masjidfeed-app'); ?></p>
                        </div>
                    <?php elseif (Masjid_Feed_Credential_Store::has_credentials() && is_wp_error(Masjid_Feed_Credential_Store::get())) : ?>
                        <div class="notice notice-error inline">
                            <p><strong><?php esc_html_e('Stored Firebase credentials cannot be decrypted.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Restore the original MASJIDFEED_CREDENTIAL_KEY or upload the service-account JSON again after intentional key rotation.', 'masjidfeed-app'); ?></p>
                        </div>
                    <?php endif; ?>
                    <table class="form-table" role="presentation">
                        <tr>
                            <th scope="row"><label for="masjidfeed_service_account"><?php esc_html_e('Service account JSON', 'masjidfeed-app'); ?></label></th>
                            <td>
                                <input type="file" id="masjidfeed_service_account" name="masjidfeed_service_account" accept="application/json,.json" />
                                <p class="description"><?php echo Masjid_Feed_Credential_Store::has_credentials() ? esc_html__('Configured. Upload another file to rotate it.', 'masjidfeed-app') : esc_html__('Not configured.', 'masjidfeed-app'); ?></p>
                                <?php if (Masjid_Feed_Credential_Store::has_credentials()) : ?><label><input type="checkbox" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[remove_firebase_credentials]" value="1" /> <?php esc_html_e('Remove stored service account', 'masjidfeed-app'); ?></label><?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="masjidfeed_ios_config"><?php esc_html_e('iOS Firebase plist', 'masjidfeed-app'); ?></label></th>
                            <td><input type="file" id="masjidfeed_ios_config" name="masjidfeed_ios_config" accept="application/xml,.plist" /><p class="description"><?php echo !empty($opts['firebase_ios_config']) ? esc_html__('Configured.', 'masjidfeed-app') : esc_html__('Not configured.', 'masjidfeed-app'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="masjidfeed_android_config"><?php esc_html_e('Android google-services.json', 'masjidfeed-app'); ?></label></th>
                            <td><input type="file" id="masjidfeed_android_config" name="masjidfeed_android_config" accept="application/json,.json" /><p class="description"><?php echo !empty($opts['firebase_android_config']) ? esc_html__('Configured.', 'masjidfeed-app') : esc_html__('Not configured.', 'masjidfeed-app'); ?></p></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Firebase project', 'masjidfeed-app'); ?></th>
                            <td><code><?php echo esc_html($opts['firebase_project_id'] ?: __('Not configured', 'masjidfeed-app')); ?></code><p class="description"><?php esc_html_e('All three uploaded files must belong to this project.', 'masjidfeed-app'); ?></p></td>
                        </tr>
                    </table>

                    <h3><?php esc_html_e('Required files and server configuration', 'masjidfeed-app'); ?></h3>
                    <h4><?php esc_html_e('Create the Firebase apps', 'masjidfeed-app'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Open Firebase Console. Create a project named MasjidApp, or open the project already used by MasjidApp.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('From Project Overview, choose Add app → Apple. Enter com.goodsoftware.masjidapp for Apple bundle ID and MasjidApp for App nickname. Register the app and download GoogleService-Info.plist.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Return to Project Overview and choose Add app → Android. Enter com.goodsoftware.masjidapp for Android package name and MasjidApp for App nickname. Register the app and download google-services.json.', 'masjidfeed-app'); ?></li>
                    </ol>
                    <p><strong><?php esc_html_e('Use these exact identifiers.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Firebase cannot change a bundle ID or package name after registration. If an existing app uses another identifier, add a new app.', 'masjidfeed-app'); ?></p>

                    <h4><?php esc_html_e('Configure push delivery', 'masjidfeed-app'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('In Firebase, open Project settings → Cloud Messaging. Under Apple app configuration, upload the APNs authentication key from the Apple Developer account and enter its Key ID and Team ID.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Open Project settings → Service accounts → Firebase Admin SDK. Choose Generate new private key, confirm, and keep the downloaded JSON file private.', 'masjidfeed-app'); ?></li>
                    </ol>

                    <h4><?php esc_html_e('Enable Firebase App Check', 'masjidfeed-app'); ?></h4>
                    <p><?php esc_html_e('Complete these cloud-side steps once for each Firebase project and registered app, not once for each WordPress server. If every tenant uses masjidapp-bcfc7 and its existing Apple and Android apps, the setup applies to all of those servers. Repeat it only when a server uses a different Firebase project or app registration.', 'masjidfeed-app'); ?></p>
                    <ol>
                        <li><?php esc_html_e('In Google Cloud Console for the Firebase project, enable the Firebase App Check API.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('In Firebase, open Security → App Check → Apps. Select the Apple app and register it with the App Attest provider.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('In Google Play Console, open Release → App integrity → Play Integrity API and link this Firebase project. Copy the production app-signing SHA-256 fingerprint.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Return to Firebase App Check, select the Android app, choose Play Integrity, and enter the production SHA-256 fingerprint.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Ask the mobile app developer to enable the App Check SDK in both apps and send its token in the X-Firebase-AppCheck header. Without this header, device registration is rejected.', 'masjidfeed-app'); ?></li>
                    </ol>
                    <p><?php esc_html_e('For test devices or simulators, the app developer must use the App Check debug provider. Add its debug token from App Check → Apps → Manage debug tokens, and never use that token in a production build.', 'masjidfeed-app'); ?></p>

                    <h4><?php esc_html_e('Server encryption key', 'masjidfeed-app'); ?></h4>
                    <p><?php esc_html_e('Generate one persistent random value on the WordPress server:', 'masjidfeed-app'); ?></p>
                    <pre><code>openssl rand -base64 48</code></pre>
                    <p><?php esc_html_e('Set the result as MASJIDFEED_CREDENTIAL_KEY in the environment used by PHP, then restart PHP or the WordPress container. The plugin reads this environment variable directly.', 'masjidfeed-app'); ?></p>
                    <pre><code>MASJIDFEED_CREDENTIAL_KEY='generated-value'</code></pre>
                    <p><?php esc_html_e('If the host cannot provide environment variables, define the value in wp-config.php before the stop-editing line:', 'masjidfeed-app'); ?></p>
                    <pre><code>define('MASJIDFEED_CREDENTIAL_KEY', 'generated-value');</code></pre>
                    <p><strong><?php esc_html_e('Store this key in deployment secret management, not in the database or source control, and preserve it across deployments.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('If it is lost or changed, upload the service-account JSON again after configuring the replacement key.', 'masjidfeed-app'); ?></p>

                    <h4><?php esc_html_e('Finish setup', 'masjidfeed-app'); ?></h4>
                    <ol>
                        <li><?php esc_html_e('Upload the service-account JSON, iOS plist, and Android google-services JSON above. All files must use the same Firebase project ID.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Save settings, choose Validate connection on the Debug tab, then paste a current device FCM token and send a test notification.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('For reliable queued delivery, configure the host scheduler to invoke WordPress cron instead of relying only on site traffic.', 'masjidfeed-app'); ?></li>
                    </ol>
                </div>
            </details>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Parking map configuration tab. Holds the finished JSON document produced
     * by the parking-geojson-to-config.py script (see parking-api/README.md).
     */
    private function render_parking_tab() {
        $raw = get_option(Masjid_Feed_Parking_Config::OPTION_KEY, '');
        $document = is_string($raw) && '' !== trim($raw) ? json_decode($raw, true) : null;
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::PARKING_OPTION_GROUP); ?>

            <h2 id="parking-config"><?php esc_html_e('Parking Map', 'masjidfeed-app'); ?></h2>
            <p class="description">
                <?php esc_html_e('Serves the parking map zones and restriction schedules consumed by the mobile app at GET /wp-json/masjid/v1/parking-config.', 'masjidfeed-app'); ?>
                <?php esc_html_e('Saving a change takes effect within a few minutes; phones pick it up the next time the app is started.', 'masjidfeed-app'); ?>
            </p>
            <p class="description">
                <?php esc_html_e('The app trusts any 200 response, so always paste the checked output of the converter script rather than editing this JSON by hand:', 'masjidfeed-app'); ?>
            </p>
            <ol style="max-width:900px;">
                <li>
                    <?php esc_html_e('Download what is live, so the endpoint stays the master copy:', 'masjidfeed-app'); ?>
                    <pre><code>curl -s -A 'ICOB-admin/1.0' '<?php echo esc_url(rest_url('masjid/v1/parking-config')); ?>' &gt; current.json</code></pre>
                </li>
                <li>
                    <?php esc_html_e('Turn it into GeoJSON and edit the zones in', 'masjidfeed-app'); ?>
                    <a href="https://geojson.io" target="_blank" rel="noopener">geojson.io</a>
                    <?php esc_html_e('(edit schedules directly in current.json instead):', 'masjidfeed-app'); ?>
                    <pre><code>python3 parking-geojson-to-config.py --to-geojson current.json &gt; zones.geojson</code></pre>
                </li>
                <li>
                    <?php esc_html_e('Build the new payload and repeat until it prints OK:', 'masjidfeed-app'); ?>
                    <pre><code>python3 parking-geojson-to-config.py zones.geojson current.json &gt; parking-config.json</code></pre>
                </li>
                <li><?php esc_html_e('Paste the contents of parking-config.json below and save.', 'masjidfeed-app'); ?></li>
            </ol>
            <p class="description">
                <?php
                printf(
                    /* translators: %s: URL of the converter script */
                    esc_html__('Download the converter script (parking-geojson-to-config.py, needs Python 3.8+) from %s. Saving invalid JSON is refused and the previous document keeps being served.', 'masjidfeed-app'),
                    '<a href="' . esc_url(self::PARKING_SCRIPT_URL) . '" target="_blank" rel="noopener">' . esc_html(self::PARKING_SCRIPT_URL) . '</a>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'masjidfeed-app'); ?></th>
                    <td>
                        <?php if (is_array($document)) : ?>
                            <p><strong><?php esc_html_e('Configured.', 'masjidfeed-app'); ?></strong>
                            <?php echo esc_html(Masjid_Feed_Parking_Config::summarize($document)); ?></p>
                            <p class="description"><?php esc_html_e('Saving invalid JSON is refused and the previous document keeps being served.', 'masjidfeed-app'); ?></p>
                        <?php else : ?>
                            <p><strong><?php esc_html_e('Not configured.', 'masjidfeed-app'); ?></strong>
                            <?php esc_html_e('The endpoint returns 404 and the app keeps using its built-in parking data.', 'masjidfeed-app'); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="masjidfeed_parking_config"><?php esc_html_e('Configuration JSON', 'masjidfeed-app'); ?></label></th>
                    <td>
                        <textarea id="masjidfeed_parking_config" name="<?php echo esc_attr(Masjid_Feed_Parking_Config::OPTION_KEY); ?>" class="large-text code" rows="24" spellcheck="false"><?php echo esc_html(is_string($raw) ? $raw : ''); ?></textarea>
                        <p class="description"><?php esc_html_e('Paste the full document, from the opening brace to the closing brace.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Effective events source: the stored key only while that plugin is
     * available. Falls back to empty ("None") otherwise, e.g. when no events
     * plugin is installed or the configured plugin was deactivated.
     */
    public static function resolve_events_source($opts) {
        $key = (string) ($opts['events_source'] ?? '');
        if ('' === $key) {
            return '';
        }
        $choices = Masjid_Feed_Event_Sources::get_choices();
        if (!isset($choices[$key]) || !$choices[$key]['available']) {
            return '';
        }
        return $key;
    }

    /**
     * Categories shown in the Events Filter for the selected events source.
     * The Events Calendar uses its own event category taxonomy.
     */
    private function get_events_filter_categories($events_source, $fallback_categories) {
        $taxonomies = Masjid_Feed_Event_Sources::get_filter_taxonomies($events_source);
        if (Masjid_Feed_Event_Source_The_Events_Calendar::EVENTS_TAXONOMY !== $taxonomies['categories']) {
            return $fallback_categories;
        }
        $terms = get_terms(array(
            'taxonomy' => $taxonomies['categories'],
            'hide_empty' => false,
        ));
        return is_wp_error($terms) ? array() : (array) $terms;
    }

    /**
     * Render a scrollable list of term checkboxes for a given field. Disabled
     * checkboxes are not submitted with the form.
     */
    private function render_term_checkboxes($terms, $field, $selected, $disabled = false) {
        if (empty($terms)) {
            echo '<p class="description">' . esc_html__('No terms found.', 'masjidfeed-app') . '</p>';
            return;
        }
        $selected = is_array($selected) ? $selected : array();
        echo '<div style="max-height:150px;overflow-y:auto;border:1px solid #ddd;padding:8px;max-width:400px;">';
        foreach ($terms as $term) {
            printf(
                '<label style="display:block;"><input type="checkbox" name="%1$s[%2$s][]" value="%3$d" %4$s %5$s /> %6$s</label>',
                esc_attr(MASJIDFEED_OPTION_KEY),
                esc_attr($field),
                (int) $term->term_id,
                checked(in_array($term->term_id, $selected, true), true, false),
                disabled($disabled, true, false),
                esc_html($term->name)
            );
        }
        echo '</div>';
    }

    public static function get_allowed_firebase_app_ids() {
        $opts = self::get_all_options();
        return array_values(array_filter(array(
            $opts['firebase_ios_config']['appId'] ?? '',
            $opts['firebase_android_config']['appId'] ?? '',
        )));
    }

    public static function get_public_firebase_config() {
        $opts = self::get_all_options();
        $public_keys = array_flip(array('appId', 'senderId', 'apiKey'));
        return array(
            'ios' => array_intersect_key($opts['firebase_ios_config'], $public_keys),
            'android' => array_intersect_key($opts['firebase_android_config'], $public_keys),
        );
    }

    public function handle_validate_firebase() {
        check_admin_referer('masjidfeed_validate_firebase');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        $checks = Masjid_Feed_Firebase::validate_configuration();
        set_transient('masjidfeed_firebase_validation', $checks, 5 * MINUTE_IN_SECONDS);
        $failed = in_array('fail', array_column($checks, 'status'), true);
        $this->redirect_with_notice(
            $failed ? 'firebase_validation_failed' : 'firebase_valid',
            $failed ? 'error' : 'success',
            'debug'
        );
    }

    public function handle_test_push() {
        check_admin_referer('masjidfeed_test_push');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        $token = sanitize_text_field(wp_unslash($_POST['fcm_token'] ?? ''));
        if ('' === $token) {
            $this->redirect_with_notice('token_required', 'error', 'debug');
        }
        try {
            (new Masjid_Feed_Firebase())->send_test($token, array(
                'title' => __('MasjidFeed App test notification', 'masjidfeed-app'),
                'body' => __('Firebase push notifications are configured.', 'masjidfeed-app'),
                'data' => array(
                    'notificationId' => wp_generate_uuid4(),
                    'masjidId' => (string) self::get_option('masjid_id'),
                    'postId' => '0',
                    'type' => 'test',
                    'deepLink' => '',
                ),
            ));
            $this->redirect_with_notice('test_sent', 'success', 'debug');
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error', 'debug');
        }
    }

    public function handle_toggle_api_tracing() {
        check_admin_referer('masjidfeed_toggle_api_tracing');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        $enabled = !empty($_POST['enabled']);
        Masjid_Feed_API_Trace::set_enabled($enabled);
        $this->redirect_with_notice($enabled ? 'api_tracing_enabled' : 'api_tracing_disabled', 'success', 'debug');
    }

    public function handle_clear_api_traces() {
        check_admin_referer('masjidfeed_clear_api_traces');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        Masjid_Feed_API_Trace::clear();
        $this->redirect_with_notice('api_traces_cleared', 'success', 'debug');
    }

    public function handle_show_settings() {
        check_admin_referer('masjidfeed_show_settings');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        set_transient(self::SETTINGS_SNAPSHOT_TRANSIENT, $this->settings_snapshot(), 5 * MINUTE_IN_SECONDS);
        $this->redirect_with_notice('settings_snapshot', 'success', 'debug');
    }

    /**
     * Build the Debug-tab settings snapshot: every key actually stored in the
     * settings option (missing keys are deliberately absent — that is the
     * diagnostic) with secret values partially obfuscated, plus related
     * plugin state that cannot be read from the option alone.
     */
    private function settings_snapshot() {
        $stored = get_option(MASJIDFEED_OPTION_KEY, array());
        $stored = is_array($stored) ? $stored : array();
        $settings = array();
        foreach ($stored as $key => $value) {
            $settings[(string) $key] = self::obfuscate_setting($value, (string) $key);
        }
        return array(
            'settings' => $settings,
            'companion' => array(
                'gateway_site_id' => self::gateway_site_id(),
                'gateway_secret' => self::obfuscate_setting(self::gateway_secret(), 'gateway_secret'),
                'gateway_challenge_stored' => '' !== (string) get_option(self::GATEWAY_CHALLENGE_OPTION, ''),
                'gateway_fetch_token_stored' => '' !== (string) get_option(self::GATEWAY_FETCH_TOKEN_OPTION, ''),
                'credential_encryption_available' => Masjid_Feed_Credential_Store::is_available(),
                'firebase_credentials_stored' => Masjid_Feed_Credential_Store::has_credentials(),
                'api_trace_enabled' => Masjid_Feed_API_Trace::is_enabled(),
                'database_version' => (string) get_option('masjidfeed_db_version', ''),
            ),
        );
    }

    /**
     * Mask string values stored under secret-looking keys. The first and
     * last characters stay visible so stored values remain comparable
     * across checks without exposing them; short values are fully masked.
     * Non-string values (booleans, counters) are not secrets and pass
     * through untouched.
     */
    public static function obfuscate_setting($value, $key = '') {
        if (is_array($value)) {
            $masked = array();
            foreach ($value as $child_key => $child_value) {
                $masked[$child_key] = self::obfuscate_setting($child_value, (string) $child_key);
            }
            return $masked;
        }
        if (!is_string($value) || '' === $value) {
            return $value;
        }
        if (!preg_match('/secret|token|key|password|credential|private|firebase_/i', $key)) {
            return $value;
        }
        if (strlen($value) <= 12) {
            return str_repeat('•', max(4, strlen($value)));
        }
        return substr($value, 0, 4) . '…' . substr($value, -4);
    }

    public function handle_register_gateway() {
        check_admin_referer('masjidfeed_register_gateway');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        try {
            $result = $this->register_with_gateway();
        } catch (Throwable $exception) {
            $message = $exception instanceof Masjid_Feed_Gateway_Exception
                ? $this->gateway_registration_error_message($exception)
                : $exception->getMessage();
            $this->redirect_with_notice(rawurlencode(substr($message, 0, 300)), 'error', 'push');
        }
        if ('pending' === $result['state']) {
            $this->redirect_with_notice(rawurlencode(__('The push service could not verify this site\'s domain during registration. Registration is pending: make sure the WordPress REST API is publicly reachable, then press Register again to retry.', 'masjidfeed-app')), 'error', 'push');
        }
        $this->redirect_with_notice(
            rawurlencode(sprintf(
                /* translators: %s: gateway site ID */
                __('Registered with the MasjidFeed push service. Site ID: %s', 'masjidfeed-app'),
                $result['site_id']
            )),
            'success',
            'push'
        );
    }

    /**
     * Map a gateway exception from the registration flow to an admin notice
     * message. The gateway's own message is shown verbatim, except when the
     * URL claim is stuck: the gateway still holds the registration an
     * earlier attempt created, but this site no longer knows that
     * registration's site ID or secret (that attempt's response was lost
     * after the gateway committed it), so the claim frees itself only when
     * the gateway operator revokes the site from the admin UI — or, for a
     * pending claim, when the 24-hour challenge expires.
     */
    private function gateway_registration_error_message(Masjid_Feed_Gateway_Exception $exception): string {
        $message = $exception->getMessage();
        if (400 === $exception->http_status() && 'validation' === $exception->error_code()) {
            if (false !== strpos($message, 'url already registered and pending verification')) {
                return __('The push service already has a pending registration for this site URL. Wait for the pending registration to expire (24 hours) and press Register again, or email masjidfeed@goodsoftware.foundation to have it revoked sooner.', 'masjidfeed-app');
            }
            if (false !== strpos($message, 'url already registered and active')) {
                return __('The push service already has an active registration for this site URL, but this site never received the one-time secret that registration issued, so it cannot authenticate. The push service must revoke that registration before this site can register again: email masjidfeed@goodsoftware.foundation to have it revoked, then press Register again.', 'masjidfeed-app');
            }
        }
        return $message;
    }

    public function handle_validate_gateway() {
        check_admin_referer('masjidfeed_validate_gateway');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        if (!self::is_gateway_registered()) {
            $this->redirect_with_notice('gateway_not_registered', 'error', 'debug');
        }
        try {
            (new Masjid_Feed_Gateway())->validate_connection();
            $this->redirect_with_notice('gateway_valid', 'success', 'debug');
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error', 'debug');
        }
    }

    public function handle_rotate_gateway_topic() {
        check_admin_referer('masjidfeed_rotate_gateway_topic');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        if (!self::is_gateway_registered()) {
            $this->redirect_with_notice('gateway_not_registered', 'error', 'push');
        }
        try {
            $version = (new Masjid_Feed_Gateway())->rotate_topic();
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error', 'push');
        }
        $this->redirect_with_notice(
            rawurlencode(sprintf(
                /* translators: %d: new topic version */
                __('Topic keys rotated; the gateway issued version %1$d. Devices re-subscribe on their next topic-key fetch, and the previous version keeps receiving notifications during the rotation grace window.', 'masjidfeed-app'),
                $version
            )),
            'success',
            'push'
        );
    }

    public function handle_fetch_gateway_audit() {
        check_admin_referer('masjidfeed_fetch_gateway_audit');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        if (!self::is_gateway_registered()) {
            $this->redirect_with_notice('gateway_not_registered', 'error', 'debug');
        }
        try {
            $entries = (new Masjid_Feed_Gateway())->site_audit();
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error', 'debug');
        }
        set_transient(self::GATEWAY_AUDIT_TRANSIENT, $entries, 5 * MINUTE_IN_SECONDS);
        $this->redirect_with_notice('gateway_audit_fetched', 'success', 'debug');
    }

    public function handle_gateway_send_status() {
        check_admin_referer('masjidfeed_gateway_send_status');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        if (!self::is_gateway_registered()) {
            $this->redirect_with_notice('gateway_not_registered', 'error', 'debug');
        }
        global $wpdb;
        $job_id = absint($_POST['job_id'] ?? 0);
        $table = $wpdb->prefix . 'masjidfeed_push_jobs';
        $sql = sprintf('SELECT * FROM %s WHERE id = %%d', esc_sql($table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- admin-only send-status lookup of the custom queue table; the table name is built from a fixed identifier and the parameter is prepared.
        $job = $wpdb->get_row($wpdb->prepare($sql, $job_id));
        $send_id = is_object($job) ? (string) $job->firebase_message_id : '';
        if ('' === $send_id) {
            $this->redirect_with_notice('gateway_send_status_missing', 'error', 'debug');
        }
        try {
            $status = (new Masjid_Feed_Gateway())->send_status($send_id);
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error', 'debug');
        }
        set_transient(self::GATEWAY_SEND_STATUS_TRANSIENT, array('send_id' => $send_id, 'record' => $status), 5 * MINUTE_IN_SECONDS);
        $this->redirect_with_notice('gateway_send_status_fetched', 'success', 'debug');
    }

    /**
     * Run the gateway onboarding flow. The plugin generates a one-time proof
     * challenge plus a random fetch token and registers: the gateway fetches
     * the challenge by callback — presenting the fetch token, which the
     * verification endpoint requires before serving anything — and returns
     * the gateway secret (shown once) in the same response. The fetch token
     * also authorizes the verify retry endpoint, so only this site can
     * complete its own onboarding. A previous attempt that failed the
     * callback left the site pending — that site is completed through the
     * verify retry endpoint instead of creating a duplicate.
     *
     * @return array{state: string, site_id: string} State is 'active' or 'pending'.
     */
    private function register_with_gateway() {
        $gateway = new Masjid_Feed_Gateway();
        $site_id = self::gateway_site_id();
        $secret = self::gateway_secret();

        if ('' !== $site_id && '' === $secret) {
            try {
                $verified = $gateway->verify_site($site_id, (string) get_option(self::GATEWAY_FETCH_TOKEN_OPTION, ''));
            } catch (Masjid_Feed_Gateway_Exception $exception) {
                $status = $exception->http_status();
                if (401 !== $status && 403 !== $status && 410 !== $status && 404 !== $status) {
                    throw $exception;
                }
                // The pending site has no retry path for THIS registration:
                // its challenge expired (410), the gateway tore the
                // registration down after a definitive verification rejection
                // (404), the stored fetch token no longer matches (401), or
                // the site is no longer pending at all (403) — already active
                // with a secret this site never received, or revoked or
                // banned by the operator. A fresh registration either
                // completes (the gateway replaces a revoked site) or answers
                // with a claim refusal this flow maps to an actionable
                // notice; retrying verify could never succeed.
                $site_id = '';
            }
            if ('' !== $site_id) {
                return $this->store_gateway_registration($verified);
            }
        }

        // The challenge and fetch token must be stored before the
        // registration POST: the gateway fetches the challenge — presenting
        // the token — during that request.
        $challenge = wp_generate_password(64, false);
        $fetch_token = wp_generate_password(64, false);
        update_option(self::GATEWAY_CHALLENGE_OPTION, $challenge, false);
        update_option(self::GATEWAY_FETCH_TOKEN_OPTION, $fetch_token, false);
        try {
            $registration = $gateway->register_site($challenge, $fetch_token);
        } catch (Throwable $exception) {
            delete_option(self::GATEWAY_CHALLENGE_OPTION);
            delete_option(self::GATEWAY_FETCH_TOKEN_OPTION);
            throw $exception;
        }
        if ('' !== $registration['gateway_secret']) {
            $this->store_gateway_registration($registration);
            return array('state' => 'active', 'site_id' => $registration['site_id']);
        }
        // Callback failed retryably: keep serving the challenge to the
        // token-holding gateway and remember the site so the next attempt
        // completes onboarding through verify_site(). store_gateway_pending_site()
        // drops the replaced registration's secret with its site ID.
        self::store_gateway_pending_site($registration['site_id']);
        return array('state' => 'pending', 'site_id' => $registration['site_id']);
    }

    /**
     * Persist a completed registration: site ID plus the one-time gateway
     * secret in the dedicated option, dropping the now-consumed proof
     * challenge and fetch token.
     */
    private function store_gateway_registration(array $registration) {
        self::save_gateway_registration($registration);
        delete_option(self::GATEWAY_CHALLENGE_OPTION);
        delete_option(self::GATEWAY_FETCH_TOKEN_OPTION);
        return array('state' => 'active', 'site_id' => $registration['site_id']);
    }

    /**
     * Sanitize the parking configuration JSON. The mobile app trusts any 200
     * response from /parking-config, so an invalid document is refused and
     * the previous document stays in place.
     */
    public function sanitize_parking_config($value) {
        $raw = is_string($value) ? trim($value) : '';
        $previous = get_option(Masjid_Feed_Parking_Config::OPTION_KEY, '');
        $previous = is_string($previous) ? $previous : '';

        if ('' === $raw) {
            return $previous;
        }

        $result = Masjid_Feed_Parking_Config::parse($raw);
        if ($result['errors']) {
            foreach (array_slice($result['errors'], 0, 10) as $index => $message) {
                add_settings_error(
                    Masjid_Feed_Parking_Config::OPTION_KEY,
                    'invalid_parking_config_' . $index,
                    $message
                );
            }
            if (count($result['errors']) > 10) {
                add_settings_error(
                    Masjid_Feed_Parking_Config::OPTION_KEY,
                    'invalid_parking_config_more',
                    sprintf(__('... and %d more problem(s).', 'masjidfeed-app'), count($result['errors']) - 10)
                );
            }
            return $previous;
        }

        return $raw;
    }

    private function sanitize_deep_link_template($value, $previous) {
        $value = trim(wp_unslash((string) $value));
        if ('' === $value) {
            return '';
        }
        $without_placeholders = str_replace(array('{masjidId}', '{postId}', '{type}', '{slug}'), 'value', $value);
        // The gateway only accepts deep links starting with the prefix
        // registered for this site (masjidapp://), so a template with any
        // other URI scheme would make every send fail validation.
        $valid = !preg_match('/[\x00-\x20\x7F]/', $value)
            && !preg_match('/[{}]/', $without_placeholders)
            && 0 === strpos($value, Masjid_Feed_Gateway::DEEP_LINK_PREFIX)
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:.+$/', $without_placeholders);
        if (!$valid) {
            add_settings_error(MASJIDFEED_OPTION_KEY, 'invalid_deep_link', __('The deep-link override is invalid. Use the masjidapp:// URI scheme and only supported placeholders.', 'masjidfeed-app'));
            return (string) $previous;
        }
        return $value;
    }

    private function process_firebase_uploads(array $out, array $input) {
        $service = $this->read_json_upload('masjidfeed_service_account');
        $ios = $this->read_plist_upload('masjidfeed_ios_config');
        $android = $this->read_json_upload('masjidfeed_android_config');
        foreach (array($service, $ios, $android) as $result) {
            if (is_wp_error($result)) {
                add_settings_error(MASJIDFEED_OPTION_KEY, $result->get_error_code(), $result->get_error_message());
                return $out;
            }
        }

        $service_config = is_array($service) ? $this->validate_service_account($service) : null;
        $ios_config = is_array($ios) ? $this->extract_ios_config($ios) : null;
        $android_config = is_array($android) ? $this->extract_android_config($android) : null;
        foreach (array($service_config, $ios_config, $android_config) as $result) {
            if (is_wp_error($result)) {
                add_settings_error(MASJIDFEED_OPTION_KEY, $result->get_error_code(), $result->get_error_message());
                return $out;
            }
        }

        $project_ids = array_filter(array(
            is_array($service_config) ? $service_config['project_id'] : $out['firebase_project_id'],
            is_array($ios_config) ? $ios_config['projectId'] : ($out['firebase_ios_config']['projectId'] ?? ''),
            is_array($android_config) ? $android_config['projectId'] : ($out['firebase_android_config']['projectId'] ?? ''),
        ));
        if (count(array_unique($project_ids)) > 1) {
            add_settings_error(MASJIDFEED_OPTION_KEY, 'firebase_project_mismatch', __('Firebase service-account, iOS, and Android files must belong to the same project.', 'masjidfeed-app'));
            return $out;
        }

        if (is_array($service_config)) {
            $saved = Masjid_Feed_Credential_Store::save($service_config);
            if (is_wp_error($saved)) {
                add_settings_error(MASJIDFEED_OPTION_KEY, $saved->get_error_code(), $saved->get_error_message());
                return $out;
            }
            $out['firebase_project_id'] = $service_config['project_id'];
        }
        if (is_array($ios_config)) {
            $out['firebase_ios_config'] = $ios_config;
            $out['firebase_project_id'] = $ios_config['projectId'];
        }
        if (is_array($android_config)) {
            $out['firebase_android_config'] = $android_config;
            $out['firebase_project_id'] = $android_config['projectId'];
        }
        if (!empty($input['remove_firebase_credentials'])) {
            Masjid_Feed_Credential_Store::delete();
        }
        return $out;
    }

    private function read_json_upload($field) {
        $contents = $this->read_upload($field);
        if (null === $contents || is_wp_error($contents)) {
            return $contents;
        }
        $decoded = json_decode($contents, true);
        return is_array($decoded) ? $decoded : new WP_Error('invalid_' . $field, __('An uploaded Firebase JSON file is invalid.', 'masjidfeed-app'));
    }

    private function read_plist_upload($field) {
        $contents = $this->read_upload($field);
        if (null === $contents || is_wp_error($contents)) {
            return $contents;
        }
        $xml = simplexml_load_string($contents, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
        if (!$xml || !isset($xml->dict)) {
            return new WP_Error('invalid_' . $field, __('The uploaded Firebase plist is invalid.', 'masjidfeed-app'));
        }
        $values = array();
        $children = $xml->dict->children();
        for ($index = 0; $index < count($children) - 1; $index += 2) {
            $values[(string) $children[$index]] = (string) $children[$index + 1];
        }
        return $values;
    }

    private function read_upload($field) {
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!wp_verify_nonce($nonce, self::GENERAL_OPTION_GROUP . '-options') && !wp_verify_nonce($nonce, self::PUSH_OPTION_GROUP . '-options')) {
            return new WP_Error('upload_' . $field, __('Firebase configuration uploads require a valid settings form.', 'masjidfeed-app'));
        }
        if (!isset($_FILES[$field], $_FILES[$field]['error'], $_FILES[$field]['size'], $_FILES[$field]['tmp_name']) || !is_array($_FILES[$field])) {
            return null;
        }
        if (UPLOAD_ERR_NO_FILE === (int) $_FILES[$field]['error']) {
            return null;
        }
        if (UPLOAD_ERR_OK !== (int) $_FILES[$field]['error'] || (int) $_FILES[$field]['size'] > 1024 * 1024) {
            return new WP_Error('upload_' . $field, __('A Firebase configuration upload failed or exceeded 1 MB.', 'masjidfeed-app'));
        }
        $tmp_name = isset($_FILES[$field]['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES[$field]['tmp_name'])) : '';
        $contents = '' !== $tmp_name && is_readable($tmp_name) ? file_get_contents($tmp_name) : false;
        return is_string($contents) ? $contents : new WP_Error('upload_' . $field, __('A Firebase configuration upload could not be read.', 'masjidfeed-app'));
    }

    private function validate_service_account(array $data) {
        foreach (array('project_id', 'client_email', 'private_key') as $key) {
            if (empty($data[$key]) || !is_string($data[$key])) {
                return new WP_Error('invalid_service_account', __('The Firebase service-account JSON is missing required fields.', 'masjidfeed-app'));
            }
        }
        return $data;
    }

    private function extract_ios_config(array $data) {
        foreach (array('GOOGLE_APP_ID', 'GCM_SENDER_ID', 'PROJECT_ID', 'API_KEY', 'BUNDLE_ID') as $key) {
            if (empty($data[$key])) {
                return new WP_Error('invalid_ios_config', __('The Firebase iOS plist is missing required fields.', 'masjidfeed-app'));
            }
        }
        return array('appId' => $data['GOOGLE_APP_ID'], 'senderId' => $data['GCM_SENDER_ID'], 'projectId' => $data['PROJECT_ID'], 'apiKey' => $data['API_KEY'], 'bundleId' => $data['BUNDLE_ID']);
    }

    private function extract_android_config(array $data) {
        $project = $data['project_info'] ?? array();
        $client = $data['client'][0] ?? array();
        $api_key = $client['api_key'][0]['current_key'] ?? '';
        $app_id = $client['client_info']['mobilesdk_app_id'] ?? '';
        $package = $client['client_info']['android_client_info']['package_name'] ?? '';
        if (empty($project['project_id']) || empty($project['project_number']) || !$api_key || !$app_id || !$package) {
            return new WP_Error('invalid_android_config', __('The Android google-services.json file is missing required fields.', 'masjidfeed-app'));
        }
        return array('appId' => $app_id, 'senderId' => (string) $project['project_number'], 'projectId' => $project['project_id'], 'apiKey' => $api_key, 'packageName' => $package);
    }

    private function render_notices() {
        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        $notice = sanitize_text_field(wp_unslash($_GET['masjidfeed_notice'] ?? ''));
        $notice_type = sanitize_key(wp_unslash($_GET['masjidfeed_notice_type'] ?? 'success'));
        if ($notice && wp_verify_nonce($nonce, self::VIEW_NONCE_ACTION)) {
            $messages = array(
                'firebase_valid' => __('Firebase configuration is valid.', 'masjidfeed-app'),
                'firebase_validation_failed' => __('Firebase configuration validation failed. See the validation results below the Firebase tools.', 'masjidfeed-app'),
                'test_sent' => __('Test notification sent.', 'masjidfeed-app'),
                'token_required' => __('Enter an FCM token.', 'masjidfeed-app'),
                'api_tracing_enabled' => __('API call tracing enabled.', 'masjidfeed-app'),
                'api_tracing_disabled' => __('API call tracing disabled.', 'masjidfeed-app'),
                'api_traces_cleared' => __('API call traces cleared.', 'masjidfeed-app'),
                'settings_snapshot' => __('Settings snapshot generated below.', 'masjidfeed-app'),
                'gateway_valid' => __('The push service accepted this site\'s credentials.', 'masjidfeed-app'),
                'gateway_not_registered' => __('This site is not registered with the MasjidFeed push service.', 'masjidfeed-app'),
                'gateway_audit_fetched' => __('Gateway audit trail fetched.', 'masjidfeed-app'),
                'gateway_send_status_fetched' => __('Gateway send status fetched.', 'masjidfeed-app'),
                'gateway_send_status_missing' => __('That notification has no send ID, so it was never delivered through the push service.', 'masjidfeed-app'),
            );
            echo '<div class="notice notice-' . esc_attr('error' === $notice_type ? 'error' : 'success') . '"><p>' . esc_html($messages[$notice] ?? rawurldecode($notice)) . '</p></div>';
        }
    }

    /**
     * Diagnostic dump of the stored settings, fetched on demand by
     * handle_show_settings(). Missing keys are meaningful: a key that does
     * not appear (for example gateway_site_id) is not stored at all, which
     * is the difference between a half-completed registration and a saved
     * one. Secret values are partially masked by obfuscate_setting().
     */
    private function render_settings_snapshot() {
        $snapshot = get_transient(self::SETTINGS_SNAPSHOT_TRANSIENT);
        delete_transient(self::SETTINGS_SNAPSHOT_TRANSIENT);
        ?>
        <h3><?php esc_html_e('Stored settings', 'masjidfeed-app'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:8px;">
            <input type="hidden" name="action" value="masjidfeed_show_settings" /><?php wp_nonce_field('masjidfeed_show_settings'); ?>
            <?php submit_button(__('Show stored settings', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <p class="description" style="max-width:900px;"><?php esc_html_e('Lists exactly what is stored in the database. Secrets are partially masked, and a setting that is not listed is not stored at all — for example a missing gateway_site_id means this site never completed its push service registration.', 'masjidfeed-app'); ?></p>
        <?php if (!is_array($snapshot)) {
            return;
        } ?>
        <?php $settings = is_array($snapshot['settings'] ?? null) ? $snapshot['settings'] : array(); ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th style="width:35%;"><?php esc_html_e('Setting', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Stored value', 'masjidfeed-app'); ?></th></tr></thead>
            <tbody>
                <?php if (!$settings) : ?>
                    <tr><td colspan="2"><?php esc_html_e('Nothing is stored yet — every setting still has its default.', 'masjidfeed-app'); ?></td></tr>
                <?php endif; ?>
                <?php foreach ($settings as $key => $value) : ?>
                    <tr>
                        <td><code><?php echo esc_html((string) $key); ?></code></td>
                        <td><?php $this->render_snapshot_value($value); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h4><?php esc_html_e('Related plugin state', 'masjidfeed-app'); ?></h4>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th style="width:35%;"><?php esc_html_e('Item', 'masjidfeed-app'); ?></th><th><?php esc_html_e('State', 'masjidfeed-app'); ?></th></tr></thead>
            <tbody>
                <?php
                $companion = is_array($snapshot['companion'] ?? null) ? $snapshot['companion'] : array();
                $rows = array(
                    'gateway_site_id' => array(__('Push service site ID', 'masjidfeed-app'), null, null),
                    'gateway_secret' => array(__('Push service secret', 'masjidfeed-app'), null, null),
                    'gateway_challenge_stored' => array(__('Gateway proof challenge', 'masjidfeed-app'), __('stored', 'masjidfeed-app'), __('not stored', 'masjidfeed-app')),
                    'gateway_fetch_token_stored' => array(__('Gateway fetch token', 'masjidfeed-app'), __('stored', 'masjidfeed-app'), __('not stored', 'masjidfeed-app')),
                    'credential_encryption_available' => array(__('Credential encryption key', 'masjidfeed-app'), __('configured', 'masjidfeed-app'), __('not configured', 'masjidfeed-app')),
                    'firebase_credentials_stored' => array(__('Firebase service account', 'masjidfeed-app'), __('stored', 'masjidfeed-app'), __('not stored', 'masjidfeed-app')),
                    'api_trace_enabled' => array(__('API call tracing', 'masjidfeed-app'), __('enabled', 'masjidfeed-app'), __('disabled', 'masjidfeed-app')),
                );
                foreach ($rows as $key => $labels) :
                    $value = $companion[$key] ?? null;
                    if (null === $labels[1]) {
                        // A plain value row: the site ID and the masked secret.
                        $state = (string) $value;
                        $state_html = '<code>' . esc_html('' !== $state ? $state : '—') . '</code>';
                    } else {
                        $state = true === $value ? $labels[1] : $labels[2];
                        $state_html = esc_html($state);
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html($labels[0]); ?></td>
                        <td><?php echo $state_html; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_html/esc_code above. ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr>
                    <td><?php esc_html_e('Database version', 'masjidfeed-app'); ?></td>
                    <td><code><?php echo esc_html((string) ($companion['database_version'] ?? '')); ?></code></td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    private function render_snapshot_value($value) {
        if (is_array($value)) {
            echo '<pre style="margin:0;white-space:pre-wrap;">' . esc_html(wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
            return;
        }
        if (is_bool($value)) {
            echo esc_html($value ? 'true' : 'false');
            return;
        }
        if (null === $value) {
            echo '<code>—</code>';
            return;
        }
        echo '<code>' . esc_html((string) $value) . '</code>';
    }

    /**
     * Gateway debug tools: connection validation, the registration audit
     * trail, and the delivery status of one queued send. Everything is
     * fetched on demand (admin-post buttons); results are rendered from
     * short-lived transients set by the handlers.
     */
    private function render_gateway_tools() {
        ?>
        <h3><?php esc_html_e('Push service tools', 'masjidfeed-app'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="action" value="masjidfeed_validate_gateway" /><?php wp_nonce_field('masjidfeed_validate_gateway'); ?>
            <?php submit_button(__('Validate push service connection', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <p class="description" style="max-width:900px;"><?php esc_html_e('Checks this site\'s registration with the hosted push service: the stored site ID and secret are verified against the gateway without sending any notification.', 'masjidfeed-app'); ?></p>
        <?php $this->render_gateway_audit_results(); ?>
        <?php $this->render_gateway_send_status_result();
    }

    /**
     * Show the registration audit trail fetched by handle_fetch_gateway_audit().
     */
    private function render_gateway_audit_results() {
        $entries = get_transient(self::GATEWAY_AUDIT_TRANSIENT);
        delete_transient(self::GATEWAY_AUDIT_TRANSIENT);
        if (!is_array($entries)) {
            return;
        }
        ?>
        <h4><?php esc_html_e('Registration audit trail', 'masjidfeed-app'); ?></h4>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-bottom:8px;">
            <input type="hidden" name="action" value="masjidfeed_fetch_gateway_audit" /><?php wp_nonce_field('masjidfeed_fetch_gateway_audit'); ?>
            <?php submit_button(__('Refresh audit trail', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <?php if (!$entries) : ?>
            <p class="description"><?php esc_html_e('The push service has no audit entries for this site.', 'masjidfeed-app'); ?></p>
            <?php return;
        endif; ?>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th style="width:30%;"><?php esc_html_e('Time (UTC)', 'masjidfeed-app'); ?></th><th style="width:30%;"><?php esc_html_e('Actor', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Action', 'masjidfeed-app'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($entries as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html((string) ($entry['at'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($entry['actor'] ?? '')); ?></td>
                        <td><code><?php echo esc_html((string) ($entry['action'] ?? '')); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Show the FCM message record fetched by handle_gateway_send_status().
     */
    private function render_gateway_send_status_result() {
        $result = get_transient(self::GATEWAY_SEND_STATUS_TRANSIENT);
        delete_transient(self::GATEWAY_SEND_STATUS_TRANSIENT);
        if (!is_array($result) || !isset($result['send_id'], $result['record'])) {
            return;
        }
        ?>
        <h4><?php esc_html_e('Send status', 'masjidfeed-app'); ?></h4>
        <p class="description"><code><?php echo esc_html((string) $result['send_id']); ?></code></p>
        <pre style="max-width:900px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html(wp_json_encode($result['record'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
        <?php
    }

    private function render_firebase_tools() {
        ?>
        <h3><?php esc_html_e('Firebase tools', 'masjidfeed-app'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="action" value="masjidfeed_validate_firebase" /><?php wp_nonce_field('masjidfeed_validate_firebase'); ?>
            <?php submit_button(__('Validate connection', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <p class="description" style="max-width:900px;"><?php esc_html_e('Runs the flow a device registration performs: decrypts the stored service-account JSON, checks the stored iOS plist and Android google-services.json configurations, fetches a fresh OAuth access token and the App Check public keys, then sends a validate-only FCM message. Raw HTTP response bodies are shown for failed steps.', 'masjidfeed-app'); ?></p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
            <input type="hidden" name="action" value="masjidfeed_test_push" /><?php wp_nonce_field('masjidfeed_test_push'); ?>
            <label for="masjidfeed_test_token"><strong><?php esc_html_e('Test FCM token', 'masjidfeed-app'); ?></strong></label><br />
            <input type="text" class="large-text code" id="masjidfeed_test_token" name="fcm_token" autocomplete="off" />
            <?php submit_button(__('Send test notification', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <?php
    }

    private function render_validation_results() {
        $checks = get_transient('masjidfeed_firebase_validation');
        delete_transient('masjidfeed_firebase_validation');
        if (!is_array($checks) || !$checks) {
            return;
        }
        ?>
        <h3><?php esc_html_e('Validation results', 'masjidfeed-app'); ?></h3>
        <table class="widefat striped" style="max-width:900px;">
            <thead><tr><th style="width:30%;"><?php esc_html_e('Check', 'masjidfeed-app'); ?></th><th style="width:10%;"><?php esc_html_e('Result', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Details', 'masjidfeed-app'); ?></th></tr></thead>
            <tbody>
                <?php foreach ($checks as $check) : ?>
                    <?php
                    $status = (string) ($check['status'] ?? 'fail');
                    $result_labels = array(
                        'pass' => __('Pass', 'masjidfeed-app'),
                        'fail' => __('Failed', 'masjidfeed-app'),
                        'skipped' => __('Skipped', 'masjidfeed-app'),
                    );
                    $colors = array('pass' => '#00a32a', 'fail' => '#d63638', 'skipped' => '#8c8f94');
                    $result_label = (string) ($result_labels[$status] ?? __('Failed', 'masjidfeed-app'));
                    $result_color = (string) ($colors[$status] ?? '#d63638');
                    $detail = (string) ($check['detail'] ?? '');
                    $response = (string) ($check['response'] ?? '');
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) ($check['label'] ?? '')); ?></td>
                        <td><strong style="color:<?php echo esc_attr($result_color); ?>;"><?php echo esc_html($result_label); ?></strong></td>
                        <td>
                            <?php echo esc_html($detail); ?>
                            <?php if ('' !== $response) : ?>
                                <details style="margin-top:6px;">
                                    <summary><?php esc_html_e('HTTP response body', 'masjidfeed-app'); ?></summary>
                                    <pre style="max-width:640px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html($response); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private function render_recent_deliveries() {
        global $wpdb;
        ?>
        <h3><?php esc_html_e('Recent deliveries', 'masjidfeed-app'); ?></h3>
        <?php
        $table = $wpdb->prefix . 'masjidfeed_push_jobs';
        $sql = sprintf('SELECT * FROM %s ORDER BY created_at DESC LIMIT 50', esc_sql($table));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- admin-only recent-deliveries view of the custom queue table; the table name is built from a fixed identifier and there are no parameters to prepare.
        $jobs = $wpdb->get_results($sql);
        if (!$jobs) {
            echo '<p>' . esc_html__('No notifications have been queued.', 'masjidfeed-app') . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Created', 'masjidfeed-app') . '</th><th>' . esc_html__('Post', 'masjidfeed-app') . '</th><th>' . esc_html__('Trigger', 'masjidfeed-app') . '</th><th>' . esc_html__('Status', 'masjidfeed-app') . '</th><th>' . esc_html__('Attempts', 'masjidfeed-app') . '</th><th>' . esc_html__('Result', 'masjidfeed-app') . '</th></tr></thead><tbody>';
        foreach ($jobs as $job) {
            echo '<tr><td>' . esc_html($job->created_at) . '</td><td><a href="' . esc_url(get_edit_post_link($job->post_id)) . '">#' . (int) $job->post_id . '</a></td><td>' . esc_html($job->trigger_type) . '</td><td>' . esc_html($job->status) . '</td><td>' . (int) $job->attempts . '</td><td>';
            if ('failed' === $job->status) {
                echo esc_html($job->error_message);
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="masjidfeed_retry_push" /><input type="hidden" name="job_id" value="' . (int) $job->id . '" />';
                wp_nonce_field('masjidfeed_retry_push_' . $job->id);
                submit_button(__('Retry', 'masjidfeed-app'), 'small', 'submit', false);
                echo '</form>';
            } else {
                echo esc_html($job->firebase_message_id ?: $job->error_message);
            }
            if ('' !== (string) $job->firebase_message_id) {
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="masjidfeed_gateway_send_status" /><input type="hidden" name="job_id" value="' . (int) $job->id . '" />';
                wp_nonce_field('masjidfeed_gateway_send_status');
                submit_button(__('Check status', 'masjidfeed-app'), 'link', 'submit', false);
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private function render_api_trace_tools() {
        $enabled = Masjid_Feed_API_Trace::is_enabled();
        $entries = Masjid_Feed_API_Trace::get_entries();
        ?>
        <h3><?php esc_html_e('API and push traces', 'masjidfeed-app'); ?></h3>
        <p>
            <strong><?php echo $enabled ? esc_html__('Enabled.', 'masjidfeed-app') : esc_html__('Disabled.', 'masjidfeed-app'); ?></strong>
            <?php esc_html_e('Captures the latest 100 MasjidFeed App API calls and Firebase sends, including timing, status, redacted parameters, and push payloads. Disable tracing when debugging is complete.', 'masjidfeed-app'); ?>
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="action" value="masjidfeed_toggle_api_tracing" />
            <input type="hidden" name="enabled" value="<?php echo $enabled ? '0' : '1'; ?>" />
            <?php wp_nonce_field('masjidfeed_toggle_api_tracing'); ?>
            <?php submit_button($enabled ? __('Disable tracing', 'masjidfeed-app') : __('Enable tracing', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <?php if ($entries) : ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;">
                <input type="hidden" name="action" value="masjidfeed_clear_api_traces" />
                <?php wp_nonce_field('masjidfeed_clear_api_traces'); ?>
                <?php submit_button(__('Clear traces', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
            </form>
            <table class="widefat striped" style="margin-top:12px;">
                <thead><tr><th><?php esc_html_e('Time (UTC)', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Activity', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Status', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Duration', 'masjidfeed-app'); ?></th><th><?php esc_html_e('App Check', 'masjidfeed-app'); ?></th><th><?php esc_html_e('Details', 'masjidfeed-app'); ?></th></tr></thead>
                <tbody>
                    <?php foreach ($entries as $entry) : ?>
                        <?php
                        $details = array(
                            'errorCode' => $entry['errorCode'] ?? '',
                            'client' => $entry['client'] ?? '',
                            'clientName' => $entry['clientName'] ?? '',
                            'clientVersion' => $entry['clientVersion'] ?? '',
                            'userAgent' => $entry['userAgent'] ?? '',
                            'parameters' => $entry['parameters'] ?? array(),
                            'payload' => $entry['payload'] ?? array(),
                        );
                        ?>
                        <tr>
                            <td><?php echo esc_html($entry['timestamp'] ?? ''); ?></td>
                            <td><code><?php echo esc_html(strtoupper($entry['method'] ?? '') . ' ' . ($entry['route'] ?? '')); ?></code></td>
                            <td><?php echo (int) ($entry['status'] ?? 0); ?></td>
                            <td><?php echo esc_html((string) ($entry['durationMs'] ?? 0)); ?> ms</td>
                            <td><?php echo esc_html($entry['appCheck'] ?? 'missing'); ?></td>
                            <td><details><summary><?php esc_html_e('View', 'masjidfeed-app'); ?></summary><pre style="max-width:520px;overflow:auto;white-space:pre-wrap;"><?php echo esc_html(wp_json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre></details></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No API calls or Firebase sends captured.', 'masjidfeed-app'); ?></p>
        <?php endif;
    }

    private function redirect_with_notice($notice, $type, $tab = '') {
        $args = array(
            'page' => self::PAGE_SLUG,
            'masjidfeed_notice' => $notice,
            'masjidfeed_notice_type' => $type,
        );
        $tab = sanitize_key((string) $tab);
        if ('' !== $tab) {
            $args['tab'] = $tab;
        }
        $url = add_query_arg($args, admin_url('options-general.php'));
        // wp_nonce_url() HTML-escapes its output; a redirect Location header must not be HTML-escaped.
        wp_safe_redirect(add_query_arg('_wpnonce', wp_create_nonce(self::VIEW_NONCE_ACTION), $url));
        exit;
    }
}
