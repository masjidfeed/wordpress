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
    const PAGE_SLUG = 'masjidfeed-app-settings';
    const VIEW_NONCE_ACTION = 'masjidfeed_settings_view';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_media_scripts'));
        add_action('admin_post_masjidfeed_validate_firebase', array($this, 'handle_validate_firebase'));
        add_action('admin_post_masjidfeed_test_push', array($this, 'handle_test_push'));
        add_action('admin_post_masjidfeed_toggle_api_tracing', array($this, 'handle_toggle_api_tracing'));
        add_action('admin_post_masjidfeed_clear_api_traces', array($this, 'handle_clear_api_traces'));
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
     * Compute default values sourced from existing WordPress site settings.
     */
    public static function get_defaults() {
        return array(
            'masjid_id' => sanitize_title(get_bloginfo('name')),
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
            'push_tag' => 0,
            'push_deep_link_template' => '',
            'firebase_project_id' => '',
            'firebase_ios_config' => array(),
            'firebase_android_config' => array(),
        );
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
    }

    public function enqueue_media_scripts($hook) {
        if ($hook !== 'settings_page_' . self::PAGE_SLUG) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('media-editor', $this->media_picker_js());
        wp_add_inline_script('wp-color-picker', $this->color_picker_js());
    }

    private function color_picker_js() {
        return <<<'JS'
jQuery(function($) {
    $('.masjidapp-color-picker').wpColorPicker();
});
JS;
    }

    private function media_picker_js() {
        return <<<'JS'
jQuery(function($) {
    $('.masjidapp-media-picker').on('click', function(e) {
        e.preventDefault();
        var button = $(this);
        var targetInput = $('#' + button.data('target'));
        var previewImg = $('#' + button.data('preview'));
        var frame = wp.media({
            title: 'Select Image',
            button: { text: 'Use this image' },
            multiple: false
        });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            targetInput.val(attachment.id);
            previewImg.attr('src', attachment.url).show();
        });
        frame.open();
    });
});
JS;
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

        $out['masjid_id'] = sanitize_title($input['masjid_id'] ?? '');
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

        $out['push_enabled'] = !empty($input['push_enabled']) ? 1 : 0;
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
        if (!in_array($current_tab, array('general', 'push', 'debug'), true)
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
            } elseif ('debug' === $current_tab) {
                $this->render_api_trace_tools();
                $this->render_firebase_tools();
                $this->render_recent_deliveries();
            } else {
                $this->render_general_tab($opts, $categories, $tags);
            }
            ?>
        </div>
        <?php
    }

    private function render_general_tab($opts, $categories, $tags) {
        ?>
        <form method="post" action="options.php">
            <?php settings_fields(self::GENERAL_OPTION_GROUP); ?>
            <input type="hidden" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[_tab]" value="general" />

            <h2><?php esc_html_e('Masjid', 'masjidfeed-app'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="masjidfeed_masjid_id"><?php esc_html_e('Masjid ID', 'masjidfeed-app'); ?></label></th>
                    <td><input type="text" id="masjidfeed_masjid_id" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[masjid_id]" value="<?php echo esc_attr($opts['masjid_id']); ?>" class="regular-text" /></td>
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
                    ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked(!empty($opts[$key])); ?> />
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
                            <option value=""><?php echo esc_html('— ' . __('None', 'masjidfeed-app') . ' —'); ?></option>
                            <?php foreach (Masjid_Feed_Event_Sources::get_choices() as $key => $choice) : ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($opts['events_source'], $key); ?> <?php disabled(!$choice['available']); ?>>
                                    <?php echo esc_html($choice['label'] . (!$choice['available'] ? __(' (not installed)', 'masjidfeed-app') : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Events Filter', 'masjidfeed-app'); ?></h2>
            <p class="description"><?php esc_html_e('Only events in the selected categories/tags will be included in the mobile app feed. Leave empty to include all events.', 'masjidfeed-app'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Categories', 'masjidfeed-app'); ?></th>
                    <td><?php $this->render_term_checkboxes($categories, 'events_categories', $opts['events_categories']); ?></td>
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
        ?>
        <form method="post" action="options.php" enctype="multipart/form-data">
            <?php settings_fields(self::PUSH_OPTION_GROUP); ?>
            <input type="hidden" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[_tab]" value="push" />

            <h2 id="push-notifications"><?php esc_html_e('Push Notifications', 'masjidfeed-app'); ?></h2>
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
            <details style="max-width:900px;margin:16px 0;">
                <summary><strong><?php esc_html_e('Required files and server configuration', 'masjidfeed-app'); ?></strong></summary>
                <div style="padding:8px 0 0 20px;">
                    <h3><?php esc_html_e('Create the Firebase apps', 'masjidfeed-app'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Open Firebase Console. Create a project named MasjidApp, or open the project already used by MasjidApp.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('From Project Overview, choose Add app → Apple. Enter com.goodsoftware.masjidapp for Apple bundle ID and MasjidApp for App nickname. Register the app and download GoogleService-Info.plist.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Return to Project Overview and choose Add app → Android. Enter com.goodsoftware.masjidapp for Android package name and MasjidApp for App nickname. Register the app and download google-services.json.', 'masjidfeed-app'); ?></li>
                    </ol>
                    <p><strong><?php esc_html_e('Use these exact identifiers.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('Firebase cannot change a bundle ID or package name after registration. If an existing app uses another identifier, add a new app.', 'masjidfeed-app'); ?></p>

                    <h3><?php esc_html_e('Configure push delivery', 'masjidfeed-app'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('In Firebase, open Project settings → Cloud Messaging. Under Apple app configuration, upload the APNs authentication key from the Apple Developer account and enter its Key ID and Team ID.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Open Project settings → Service accounts → Firebase Admin SDK. Choose Generate new private key, confirm, and keep the downloaded JSON file private.', 'masjidfeed-app'); ?></li>
                    </ol>

                    <h3><?php esc_html_e('Enable Firebase App Check', 'masjidfeed-app'); ?></h3>
                    <p><?php esc_html_e('Complete these cloud-side steps once for each Firebase project and registered app, not once for each WordPress server. If every tenant uses masjidapp-bcfc7 and its existing Apple and Android apps, the setup applies to all of those servers. Repeat it only when a server uses a different Firebase project or app registration.', 'masjidfeed-app'); ?></p>
                    <ol>
                        <li><?php esc_html_e('In Google Cloud Console for the Firebase project, enable the Firebase App Check API.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('In Firebase, open Security → App Check → Apps. Select the Apple app and register it with the App Attest provider.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('In Google Play Console, open Release → App integrity → Play Integrity API and link this Firebase project. Copy the production app-signing SHA-256 fingerprint.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Return to Firebase App Check, select the Android app, choose Play Integrity, and enter the production SHA-256 fingerprint.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Ask the mobile app developer to enable the App Check SDK in both apps and send its token in the X-Firebase-AppCheck header. Without this header, device registration is rejected.', 'masjidfeed-app'); ?></li>
                    </ol>
                    <p><?php esc_html_e('For test devices or simulators, the app developer must use the App Check debug provider. Add its debug token from App Check → Apps → Manage debug tokens, and never use that token in a production build.', 'masjidfeed-app'); ?></p>

                    <h3><?php esc_html_e('Server encryption key', 'masjidfeed-app'); ?></h3>
                    <p><?php esc_html_e('Generate one persistent random value on the WordPress server:', 'masjidfeed-app'); ?></p>
                    <pre><code>openssl rand -base64 48</code></pre>
                    <p><?php esc_html_e('Set the result as MASJIDFEED_CREDENTIAL_KEY in the environment used by PHP, then restart PHP or the WordPress container. The plugin reads this environment variable directly.', 'masjidfeed-app'); ?></p>
                    <pre><code>MASJIDFEED_CREDENTIAL_KEY='generated-value'</code></pre>
                    <p><?php esc_html_e('If the host cannot provide environment variables, define the value in wp-config.php before the stop-editing line:', 'masjidfeed-app'); ?></p>
                    <pre><code>define('MASJIDFEED_CREDENTIAL_KEY', 'generated-value');</code></pre>
                    <p><strong><?php esc_html_e('Store this key in deployment secret management, not in the database or source control, and preserve it across deployments.', 'masjidfeed-app'); ?></strong> <?php esc_html_e('If it is lost or changed, upload the service-account JSON again after configuring the replacement key.', 'masjidfeed-app'); ?></p>

                    <h3><?php esc_html_e('Finish setup', 'masjidfeed-app'); ?></h3>
                    <ol>
                        <li><?php esc_html_e('Upload the service-account JSON, iOS plist, and Android google-services JSON below. All files must use the same Firebase project ID.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('Save settings, choose Validate connection, then paste a current device FCM token and send a test notification.', 'masjidfeed-app'); ?></li>
                        <li><?php esc_html_e('For reliable queued delivery, configure the host scheduler to invoke WordPress cron instead of relying only on site traffic.', 'masjidfeed-app'); ?></li>
                    </ol>
                </div>
            </details>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable push notifications', 'masjidfeed-app'); ?></th>
                    <td><label><input type="checkbox" name="<?php echo esc_attr(MASJIDFEED_OPTION_KEY); ?>[push_enabled]" value="1" <?php checked(!empty($opts['push_enabled'])); ?> /> <?php esc_html_e('Register devices and send notifications', 'masjidfeed-app'); ?></label></td>
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
                        <p class="description"><?php esc_html_e('Optional. Supports {masjidId}, {postId}, {type}, and {slug}. Any URI scheme is allowed; leave blank for the built-in event or announcement link.', 'masjidfeed-app'); ?></p>
                    </td>
                </tr>
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

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render a scrollable list of term checkboxes for a given field.
     */
    private function render_term_checkboxes($terms, $field, $selected) {
        if (empty($terms)) {
            echo '<p class="description">' . esc_html__('No terms found.', 'masjidfeed-app') . '</p>';
            return;
        }
        $selected = is_array($selected) ? $selected : array();
        echo '<div style="max-height:150px;overflow-y:auto;border:1px solid #ddd;padding:8px;max-width:400px;">';
        foreach ($terms as $term) {
            printf(
                '<label style="display:block;"><input type="checkbox" name="%1$s[%2$s][]" value="%3$d" %4$s /> %5$s</label>',
                esc_attr(MASJIDFEED_OPTION_KEY),
                esc_attr($field),
                (int) $term->term_id,
                checked(in_array($term->term_id, $selected, true), true, false),
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
        try {
            (new Masjid_Feed_Firebase())->validate();
            $this->redirect_with_notice('firebase_valid', 'success');
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error');
        }
    }

    public function handle_test_push() {
        check_admin_referer('masjidfeed_test_push');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        $token = sanitize_text_field(wp_unslash($_POST['fcm_token'] ?? ''));
        if ('' === $token) {
            $this->redirect_with_notice('token_required', 'error');
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
            $this->redirect_with_notice('test_sent', 'success');
        } catch (Throwable $exception) {
            $this->redirect_with_notice(rawurlencode(substr($exception->getMessage(), 0, 300)), 'error');
        }
    }

    public function handle_toggle_api_tracing() {
        check_admin_referer('masjidfeed_toggle_api_tracing');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        $enabled = !empty($_POST['enabled']);
        Masjid_Feed_API_Trace::set_enabled($enabled);
        $this->redirect_with_notice($enabled ? 'api_tracing_enabled' : 'api_tracing_disabled', 'success');
    }

    public function handle_clear_api_traces() {
        check_admin_referer('masjidfeed_clear_api_traces');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to manage MasjidFeed App settings.', 'masjidfeed-app'));
        }
        Masjid_Feed_API_Trace::clear();
        $this->redirect_with_notice('api_traces_cleared', 'success');
    }

    private function sanitize_deep_link_template($value, $previous) {
        $value = trim(wp_unslash((string) $value));
        if ('' === $value) {
            return '';
        }
        $without_placeholders = str_replace(array('{masjidId}', '{postId}', '{type}', '{slug}'), 'value', $value);
        $valid = !preg_match('/[\x00-\x20\x7F]/', $value)
            && !preg_match('/[{}]/', $without_placeholders)
            && preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:.+$/', $without_placeholders);
        if (!$valid) {
            add_settings_error(MASJIDFEED_OPTION_KEY, 'invalid_deep_link', __('The deep-link override is invalid. Use a URI scheme and only supported placeholders.', 'masjidfeed-app'));
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
        if (!empty($_GET['settings-updated'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'masjidfeed-app') . '</p></div>';
        }
        $notice = sanitize_text_field(wp_unslash($_GET['masjidfeed_notice'] ?? ''));
        $notice_type = sanitize_key(wp_unslash($_GET['masjidfeed_notice_type'] ?? 'success'));
        if ($notice && wp_verify_nonce($nonce, self::VIEW_NONCE_ACTION)) {
            $messages = array(
                'firebase_valid' => __('Firebase configuration is valid.', 'masjidfeed-app'),
                'test_sent' => __('Test notification sent.', 'masjidfeed-app'),
                'token_required' => __('Enter an FCM token.', 'masjidfeed-app'),
                'api_tracing_enabled' => __('API call tracing enabled.', 'masjidfeed-app'),
                'api_tracing_disabled' => __('API call tracing disabled.', 'masjidfeed-app'),
                'api_traces_cleared' => __('API call traces cleared.', 'masjidfeed-app'),
            );
            echo '<div class="notice notice-' . esc_attr('error' === $notice_type ? 'error' : 'success') . '"><p>' . esc_html($messages[$notice] ?? rawurldecode($notice)) . '</p></div>';
        }
    }

    private function render_firebase_tools() {
        ?>
        <h3><?php esc_html_e('Firebase tools', 'masjidfeed-app'); ?></h3>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-right:8px;">
            <input type="hidden" name="action" value="masjidfeed_validate_firebase" /><?php wp_nonce_field('masjidfeed_validate_firebase'); ?>
            <?php submit_button(__('Validate connection', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
            <input type="hidden" name="action" value="masjidfeed_test_push" /><?php wp_nonce_field('masjidfeed_test_push'); ?>
            <label for="masjidfeed_test_token"><strong><?php esc_html_e('Test FCM token', 'masjidfeed-app'); ?></strong></label><br />
            <input type="text" class="large-text code" id="masjidfeed_test_token" name="fcm_token" autocomplete="off" />
            <?php submit_button(__('Send test notification', 'masjidfeed-app'), 'secondary', 'submit', false); ?>
        </form>
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
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"><input type="hidden" name="action" value="masjidfeed_retry_push" /><input type="hidden" name="job_id" value="' . (int) $job->id . '" />';
                wp_nonce_field('masjidfeed_retry_push_' . $job->id);
                submit_button(__('Retry', 'masjidfeed-app'), 'small', 'submit', false);
                echo '</form>';
            } else {
                echo esc_html($job->firebase_message_id ?: $job->error_message);
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

    private function redirect_with_notice($notice, $type) {
        wp_safe_redirect(wp_nonce_url(
            add_query_arg(array('page' => self::PAGE_SLUG, 'masjidfeed_notice' => $notice, 'masjidfeed_notice_type' => $type), admin_url('options-general.php')),
            self::VIEW_NONCE_ACTION
        ));
        exit;
    }
}
