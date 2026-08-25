<?php
/**
 * Uninstall handler for Masjid App
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('masjidapp_settings');
delete_option('masjidapp_firebase_credentials');
delete_option('masjidapp_api_trace_enabled');
delete_option('masjidapp_api_trace_entries');
delete_option('masjidapp_db_version');

wp_clear_scheduled_hook('masjidapp_maybe_enqueue_first_publish');
wp_clear_scheduled_hook('masjidapp_process_push_job');
wp_clear_scheduled_hook('masjidapp_cleanup_push_jobs');

global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}masjidapp_push_jobs");
