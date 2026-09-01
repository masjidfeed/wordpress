<?php
/**
 * Uninstall handler for Masjid App
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('masjidfeed_settings');
delete_option('masjidfeed_firebase_credentials');
delete_option('masjidfeed_api_trace_enabled');
delete_option('masjidfeed_api_trace_entries');
delete_option('masjidfeed_db_version');

wp_clear_scheduled_hook('masjidfeed_maybe_enqueue_first_publish');
wp_clear_scheduled_hook('masjidfeed_process_push_job');
wp_clear_scheduled_hook('masjidfeed_cleanup_push_jobs');

global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange -- uninstallation must remove the custom queue table.
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}masjidfeed_push_jobs");
