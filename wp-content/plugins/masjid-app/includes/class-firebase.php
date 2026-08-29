<?php
/**
 * Firebase Admin SDK gateway.
 */

if (!defined('ABSPATH')) { exit; }

use Masjid_App\Dependencies\Kreait\Firebase\Factory;
use Masjid_App\Dependencies\Kreait\Firebase\Messaging\CloudMessage;

class Masjid_App_Firebase {

    private $factory;

    public function __construct() {
        $credentials = Masjid_App_Credential_Store::get();
        if (is_wp_error($credentials)) {
            throw new RuntimeException($credentials->get_error_message());
        }
        $this->factory = (new Factory())->withServiceAccount($credentials);
    }

    public function verify_app_check($token, array $allowed_app_ids) {
        $verified = $this->factory->createAppCheck()->verifyToken($token);
        if (!in_array($verified->appId, $allowed_app_ids, true)) {
            throw new RuntimeException(__('The App Check token belongs to an unconfigured Firebase application.', 'masjid-app'));
        }
        return $verified->appId;
    }

    public function subscribe($token) {
        return $this->factory->createMessaging()->subscribeToTopic($this->topic(), $token);
    }

    public function unsubscribe($token) {
        return $this->factory->createMessaging()->unsubscribeFromTopic($this->topic(), $token);
    }

    public function send_topic(array $payload) {
        return $this->send('topic', $this->topic(), $payload);
    }

    public function send_test($token, array $payload) {
        return $this->send('token', $token, $payload);
    }

    public function validate() {
        $payload = array(
            'title' => __('MasjidFeed App Firebase validation', 'masjid-app'),
            'body' => __('Configuration validated successfully.', 'masjid-app'),
            'data' => array('type' => 'validation'),
        );
        return $this->factory->createMessaging()->send($this->message('topic', $this->topic(), $payload), true);
    }

    private function send($target_type, $target, array $payload) {
        $started_at = microtime(true);
        try {
            $result = $this->factory->createMessaging()->send($this->message($target_type, $target, $payload));
            $this->trace_push($target_type, $payload, 200, $started_at);
            return $result;
        } catch (Throwable $exception) {
            $this->trace_push($target_type, $payload, 502, $started_at, get_class($exception));
            throw $exception;
        }
    }

    private function trace_push($target_type, array $payload, $status, $started_at, $error_code = '') {
        if (!class_exists('Masjid_App_API_Trace')) {
            return;
        }
        try {
            Masjid_App_API_Trace::record_push($target_type, $payload, $status, (microtime(true) - $started_at) * 1000, $error_code);
        } catch (Throwable) {
        }
    }

    private function message($target_type, $target, array $payload) {
        $notification = array(
            'title' => (string) $payload['title'],
            'body' => (string) $payload['body'],
        );
        $image_url = esc_url_raw((string) ($payload['image'] ?? ''));
        if ('' !== $image_url) {
            $notification['image'] = $image_url;
        }

        $message = CloudMessage::withTarget($target_type, $target)
            ->withNotification($notification)
            ->withData(array_map('strval', $payload['data']));
        if ('' !== $image_url) {
            $message = $message->withApnsConfig(array(
                'payload' => array('aps' => array('mutable-content' => 1)),
                'fcm_options' => array('image' => $image_url),
            ));
        }
        return $message;
    }

    private function topic() {
        $masjid_id = Masjid_App_Settings::get_option('masjid_id');
        return substr('masjid_' . preg_replace('/[^A-Za-z0-9-_.~%]/', '_', (string) $masjid_id), 0, 900);
    }
}