<?php
/**
 * Push notification queue and post publishing integration.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_App_Push_Notifications {

    const MAX_ATTEMPTS = 5;

    public function __construct() {
        add_action('transition_post_status', array($this, 'maybe_enqueue_first_publish'), 20, 3);
        add_action('masjidapp_maybe_enqueue_first_publish', array($this, 'enqueue_first_publish'));
        add_action('masjidapp_process_push_job', array($this, 'process_job'));
        add_action('masjidapp_cleanup_push_jobs', array($this, 'cleanup_jobs'));
        add_action('add_meta_boxes_post', array($this, 'add_meta_box'));
        add_action('admin_post_masjidapp_resend_push', array($this, 'handle_resend'));
        add_action('admin_post_masjidapp_retry_push', array($this, 'handle_retry'));
    }

    public static function create_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'masjidapp_push_jobs';
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_id CHAR(36) NOT NULL,
            post_id BIGINT(20) UNSIGNED NOT NULL,
            trigger_type VARCHAR(20) NOT NULL,
            dedupe_key VARCHAR(191) DEFAULT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            next_attempt_at DATETIME DEFAULT NULL,
            firebase_message_id VARCHAR(255) DEFAULT NULL,
            error_message TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            sent_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY notification_id (notification_id),
            UNIQUE KEY dedupe_key (dedupe_key),
            KEY status_next_attempt (status, next_attempt_at),
            KEY post_id (post_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function maybe_enqueue_first_publish($new_status, $old_status, $post) {
        if ('publish' !== $new_status || 'publish' === $old_status || 'post' !== $post->post_type) {
            return;
        }
        wp_schedule_single_event(time() + 1, 'masjidapp_maybe_enqueue_first_publish', array((int) $post->ID));
    }

    public function enqueue_first_publish($post_id) {
        if ($this->post_is_eligible($post_id)) {
            $this->enqueue($post_id, 'publish', 'publish:' . $post_id);
        }
    }

    public function enqueue($post_id, $trigger_type, $dedupe_key = null) {
        global $wpdb;
        $notification_id = wp_generate_uuid4();
        $payload = $this->build_payload($post_id, $notification_id);
        if (is_wp_error($payload)) {
            return $payload;
        }

        $now = current_time('mysql', true);
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'masjidapp_push_jobs',
            array(
                'notification_id' => $notification_id,
                'post_id' => absint($post_id),
                'trigger_type' => sanitize_key($trigger_type),
                'dedupe_key' => $dedupe_key,
                'payload' => wp_json_encode($payload),
                'status' => 'pending',
                'next_attempt_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ),
            array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        if (!$inserted) {
            return new WP_Error('masjidapp_push_not_enqueued', __('The notification was already queued or could not be saved.', 'masjid-app'));
        }

        $job_id = (int) $wpdb->insert_id;
        wp_schedule_single_event(time(), 'masjidapp_process_push_job', array($job_id));
        return $job_id;
    }

    public function process_job($job_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'masjidapp_push_jobs';
        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $job_id));
        if (!$job || !in_array($job->status, array('pending', 'retry'), true)) {
            return;
        }

        $claimed = $wpdb->update($table, array('status' => 'sending', 'updated_at' => current_time('mysql', true)), array('id' => $job_id, 'status' => $job->status));
        if (!$claimed) {
            return;
        }

        try {
            $payload = json_decode($job->payload, true, 512, JSON_THROW_ON_ERROR);
            $result = (new Masjid_App_Firebase())->send_topic($payload);
            $wpdb->update($table, array(
                'status' => 'sent',
                'attempts' => (int) $job->attempts + 1,
                'firebase_message_id' => sanitize_text_field($result['name'] ?? ''),
                'error_message' => null,
                'sent_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ), array('id' => $job_id));
        } catch (Throwable $exception) {
            $attempts = (int) $job->attempts + 1;
            $retry = $attempts < self::MAX_ATTEMPTS && $this->is_retryable($exception);
            $delay = $this->get_retry_delay($exception, $attempts);
            $next = gmdate('Y-m-d H:i:s', time() + $delay);
            $wpdb->update($table, array(
                'status' => $retry ? 'retry' : 'failed',
                'attempts' => $attempts,
                'next_attempt_at' => $retry ? $next : null,
                'error_message' => sanitize_textarea_field(substr($exception->getMessage(), 0, 1000)),
                'updated_at' => current_time('mysql', true),
            ), array('id' => $job_id));
            if ($retry) {
                wp_schedule_single_event(time() + $delay, 'masjidapp_process_push_job', array((int) $job_id));
            }
        }
    }

    public function cleanup_jobs() {
        global $wpdb;
        $table = $wpdb->prefix . 'masjidapp_push_jobs';
        $wpdb->query("DELETE FROM $table WHERE created_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)");
    }

    public function add_meta_box() {
        add_meta_box('masjidapp-push', __('MasjidFeed App Push', 'masjid-app'), array($this, 'render_meta_box'), 'post', 'side');
    }

    public function render_meta_box($post) {
        if (!$this->post_is_eligible($post->ID)) {
            echo '<p>' . esc_html__('Assign the configured push tag to enable notifications.', 'masjid-app') . '</p>';
            return;
        }
        if ('publish' !== $post->post_status) {
            echo '<p>' . esc_html__('Publish this post before sending a notification.', 'masjid-app') . '</p>';
            return;
        }
        $url = wp_nonce_url(admin_url('admin-post.php?action=masjidapp_resend_push&post_id=' . $post->ID), 'masjidapp_resend_push_' . $post->ID);
        echo '<p><a class="button" href="' . esc_url($url) . '">' . esc_html__('Send notification', 'masjid-app') . '</a></p>';
    }

    public function handle_resend() {
        $post_id = absint($_GET['post_id'] ?? 0);
        if (!current_user_can('edit_post', $post_id) || !check_admin_referer('masjidapp_resend_push_' . $post_id)) {
            wp_die(esc_html__('You are not allowed to send this notification.', 'masjid-app'));
        }
        $post = get_post($post_id);
        if (!$post || 'publish' !== $post->post_status || !$this->post_is_eligible($post_id)) {
            wp_die(esc_html__('Only tagged, published posts can send notifications.', 'masjid-app'));
        }
        $this->enqueue($post_id, 'manual');
        wp_safe_redirect(get_edit_post_link($post_id, 'raw'));
        exit;
    }

    public function handle_retry() {
        $job_id = absint($_POST['job_id'] ?? 0);
        if (!current_user_can('manage_options') || !check_admin_referer('masjidapp_retry_push_' . $job_id)) {
            wp_die(esc_html__('You are not allowed to retry this notification.', 'masjid-app'));
        }
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'masjidapp_push_jobs', array('status' => 'retry', 'next_attempt_at' => current_time('mysql', true)), array('id' => $job_id, 'status' => 'failed'));
        wp_schedule_single_event(time(), 'masjidapp_process_push_job', array($job_id));
        wp_safe_redirect(admin_url('options-general.php?page=' . Masjid_App_Settings::PAGE_SLUG));
        exit;
    }

    private function post_is_eligible($post_id) {
        if (!Masjid_App_Settings::get_option('push_enabled')) {
            return false;
        }
        $tag_id = absint(Masjid_App_Settings::get_option('push_tag'));
        return $tag_id && has_term($tag_id, 'post_tag', $post_id);
    }

    private function build_payload($post_id, $notification_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('masjidapp_post_missing', __('The notification post no longer exists.', 'masjid-app'));
        }
        $type = '1' === get_post_meta($post_id, '_icob_event_date_enabled', true) ? 'events' : 'announcements';
        $masjid_id = (string) Masjid_App_Settings::get_option('masjid_id');
        $template = (string) Masjid_App_Settings::get_option('push_deep_link_template');
        if ('' === $template) {
            $template = 'masjidapp://open/v1/masjids/{masjidId}/' . $type . '/{postId}';
        }
        $deep_link = strtr($template, array(
            '{masjidId}' => rawurlencode($masjid_id),
            '{postId}' => (string) $post_id,
            '{type}' => rawurlencode($type),
            '{slug}' => rawurlencode($post->post_name),
        ));
        $source = '' !== trim($post->post_excerpt) ? $post->post_excerpt : wp_strip_all_tags(apply_filters('the_content', $post->post_content));
        $source = html_entity_decode($source, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = wp_html_excerpt(preg_replace('/\s+/', ' ', $source), 180, '...');
        $payload = array(
            'title' => html_entity_decode(wp_strip_all_tags(get_the_title($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'body' => $body,
            'data' => array(
                'notificationId' => $notification_id,
                'masjidId' => $masjid_id,
                'postId' => (string) $post_id,
                'type' => $type,
                'deepLink' => $deep_link,
            ),
        );
        $image_url = get_the_post_thumbnail_url($post_id, 'large');
        if ($image_url) {
            $payload['image'] = esc_url_raw($image_url);
        }
        return $payload;
    }

    private function is_retryable($exception) {
        return $exception instanceof Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\ApiConnectionFailed
            || $exception instanceof Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\QuotaExceeded
            || $exception instanceof Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\ServerError
            || $exception instanceof Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging\ServerUnavailable;
    }

    private function get_retry_delay($exception, $attempts) {
        if (method_exists($exception, 'retryAfter') && $exception->retryAfter()) {
            return max(MINUTE_IN_SECONDS, $exception->retryAfter()->getTimestamp() - time());
        }
        return min(HOUR_IN_SECONDS, (2 ** $attempts) * MINUTE_IN_SECONDS);
    }
}