<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

class FirebaseMessageTest extends FirebaseClientTestCase {

    private Masjid_Feed_Firebase $firebase;

    protected function setUp(): void {
        parent::setUp();
        $this->firebase = (new ReflectionClass(Masjid_Feed_Firebase::class))->newInstanceWithoutConstructor();
    }

    private function message(string $target_type, string $target, array $payload): array {
        $reflection = new ReflectionMethod(Masjid_Feed_Firebase::class, 'message');
        return $reflection->invoke($this->firebase, $target_type, $target, $payload);
    }

    private function payload(): array {
        return [
            'title' => 'Title',
            'body' => 'Body',
            'data' => ['postId' => 42, 'deepLink' => 'masjidapp://open'],
        ];
    }

    public function test_topic_message_uses_fcm_v1_shape(): void {
        $message = $this->message('topic', 'masjid_icob', $this->payload());
        $this->assertSame('masjid_icob', $message['message']['topic']);
        $this->assertSame(['title' => 'Title', 'body' => 'Body'], $message['message']['notification']);
        $this->assertSame(['postId' => '42', 'deepLink' => 'masjidapp://open'], $message['message']['data']);
        $this->assertArrayNotHasKey('apns', $message['message']);
    }

    public function test_token_message_targets_registration_token(): void {
        $message = $this->message('token', 'device-token', $this->payload());
        $this->assertSame('device-token', $message['message']['token']);
        $this->assertArrayNotHasKey('topic', $message['message']);
    }

    public function test_image_adds_notification_image_and_apns_config(): void {
        $payload = $this->payload();
        $payload['image'] = 'https://example.org/image.jpg';
        $message = $this->message('topic', 'masjid_icob', $payload);
        $this->assertSame('https://example.org/image.jpg', $message['message']['notification']['image']);
        $this->assertSame(
            [
                'payload' => ['aps' => ['mutable-content' => 1]],
                'fcm_options' => ['image' => 'https://example.org/image.jpg'],
            ],
            $message['message']['apns']
        );
    }

    public function test_invalid_image_url_is_dropped(): void {
        $payload = $this->payload();
        $payload['image'] = 'not a url';
        $message = $this->message('topic', 'masjid_icob', $payload);
        $this->assertArrayNotHasKey('image', $message['message']['notification']);
        $this->assertArrayNotHasKey('apns', $message['message']);
    }
}
