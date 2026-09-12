<?php
/**
 * Plugin Name: MasjidFeed App
 * Plugin URI: https://github.com/masjidfeed/wordpress
 * Description: WordPress backend for the MasjidFeed mobile app. Powers the app's config, prayer times, events, and announcements over a REST API.
 * Version: 1.0.1
 * Author: stankovski
 * Author URI: https://goodsoftware.foundation/
 * Text Domain: masjidfeed-app
 * Requires at least: 6.0
 * Tested up to: 7.1
 * Requires PHP: 8.1
 * Requires Plugins: muslim-prayer-times
 * License: MIT
 * License URI: https://github.com/masjidfeed/wordpress/blob/main/LICENSE
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MASJIDFEED_VERSION', '1.0.1');
define('MASJIDFEED_PLUGIN_FILE', __FILE__);
define('MASJIDFEED_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MASJIDFEED_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MASJIDFEED_OPTION_KEY', 'masjidfeed_settings');
define('MASJIDFEED_CREDENTIAL_OPTION_KEY', 'masjidfeed_firebase_credentials');
define('MASJIDFEED_DB_VERSION', '1.1.0');

/**
 * Main Masjid App Plugin Class
 */
class Masjid_Feed_Plugin {

    /**
     * Single instance of the plugin
     */
    private static $instance = null;

    /**
     * Get the single instance of the plugin
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_notices', array($this, 'dependency_notice'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        $this->load_includes();
        if (MASJIDFEED_DB_VERSION !== get_option('masjidfeed_db_version')) {
            Masjid_Feed_Push_Notifications::create_table();
            update_option('masjidfeed_db_version', MASJIDFEED_DB_VERSION, false);
        }
        if (!wp_next_scheduled('masjidfeed_cleanup_push_jobs')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'masjidfeed_cleanup_push_jobs');
        }
    }

    /**
     * Load plugin includes
     */
    private function load_includes() {
        require_once MASJIDFEED_PLUGIN_DIR . 'vendor-prefixed/autoload.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-credential-store.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-firebase-exceptions.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-firebase-client.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-firebase.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-push-notifications.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-api-trace.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-legacy-settings-migrator.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-settings.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-content-only-renderer.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-event-sources.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-event-source-awesome-calendar-events.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-event-source-the-events-calendar.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-rest-api.php';

        new Masjid_Feed_API_Trace();
        new Masjid_Feed_Legacy_Settings_Migrator();
        new Masjid_Feed_Settings();
        new Masjid_Feed_Content_Only_Renderer();
        new Masjid_Feed_REST_API();
        new Masjid_Feed_Push_Notifications();
    }

    public function activate() {
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-credential-store.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-push-notifications.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-api-trace.php';
        require_once MASJIDFEED_PLUGIN_DIR . 'includes/class-legacy-settings-migrator.php';
        Masjid_Feed_Legacy_Settings_Migrator::migrate();
        Masjid_Feed_Push_Notifications::create_table();
        update_option('masjidfeed_db_version', MASJIDFEED_DB_VERSION, false);
        if (!wp_next_scheduled('masjidfeed_cleanup_push_jobs')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'masjidfeed_cleanup_push_jobs');
        }
    }

    public function deactivate() {
        wp_clear_scheduled_hook('masjidfeed_maybe_enqueue_first_publish');
        wp_clear_scheduled_hook('masjidfeed_process_push_job');
        wp_clear_scheduled_hook('masjidfeed_cleanup_push_jobs');
    }

    /**
     * Warn admins if required companion plugins are missing/inactive.
     */
    public function dependency_notice() {
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $missing = array();
        if (!function_exists('muslprti_salah_api_endpoint')) {
            $missing[] = 'Muslim Prayer Times';
        }

        if (empty($missing)) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s: comma separated list of missing plugin names */
                __('MasjidFeed App requires the following plugin(s) to be active: %s', 'masjidfeed-app'),
                implode(', ', $missing)
            ))
        );
    }
}

Masjid_Feed_Plugin::get_instance();
