<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

class PushNotificationsPayloadTest extends FirebaseClientTestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__test_thumbnail_urls'] = [];
        $GLOBALS['__test_post_meta'] = [];
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_id' => 'masjid-x',
            'push_deep_link_template' => '',
        ];
    }

    private function build_payload(int $post_id): array {
        $notifications = new Masjid_Feed_Push_Notifications();
        $reflection = new ReflectionMethod(Masjid_Feed_Push_Notifications::class, 'build_payload');
        return $reflection->invoke($notifications, $post_id, 'notification-uuid');
    }

    private function queue_post(): void {
        $GLOBALS['__test_posts'][5] = new WP_Post([
            'ID' => 5,
            'post_title' => 'Parking update',
            'post_excerpt' => 'North lot closed Friday.',
            'post_content' => '<p>Ignored because the excerpt wins.</p>',
            'post_name' => 'parking-update',
            'post_type' => 'post',
            'post_status' => 'publish',
        ]);
    }

    public function test_payload_carries_every_property_the_app_contract_defines(): void {
        $this->queue_post();
        $GLOBALS['__test_thumbnail_urls'][5] = 'https://example.org/uploads/parking.jpg';

        $payload = $this->build_payload(5);

        $this->assertSame('Parking update', $payload['title']);
        $this->assertSame('North lot closed Friday.', $payload['body']);
        $this->assertSame('https://example.org/uploads/parking.jpg', $payload['image']);
        $this->assertSame([
            'notificationId' => 'notification-uuid',
            'masjidId' => 'masjid-x',
            'postId' => '5',
            'type' => 'announcements',
            'deepLink' => 'masjidapp://open/v1/masjids/masjid-x/announcements/5',
        ], $payload['data']);
    }

    public function test_payload_drops_non_https_image(): void {
        $this->queue_post();
        $GLOBALS['__test_thumbnail_urls'][5] = 'http://example.org/uploads/parking.jpg';

        $payload = $this->build_payload(5);

        $this->assertArrayNotHasKey('image', $payload);
    }

    public function test_payload_drops_invalid_image_url(): void {
        $this->queue_post();
        $GLOBALS['__test_thumbnail_urls'][5] = 'not a url';

        $payload = $this->build_payload(5);

        $this->assertArrayNotHasKey('image', $payload);
    }

    public function test_payload_omits_image_without_featured_image(): void {
        $this->queue_post();

        $payload = $this->build_payload(5);

        $this->assertArrayNotHasKey('image', $payload);
    }

    public function test_payload_applies_the_deep_link_template(): void {
        $this->queue_post();
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]['push_deep_link_template'] =
            'masjidapp://open/v1/masjids/{masjidId}/{type}/{postId}';

        $payload = $this->build_payload(5);

        $this->assertSame('masjidapp://open/v1/masjids/masjid-x/announcements/5', $payload['data']['deepLink']);
    }

    public function test_payload_types_event_posts_as_events(): void {
        $GLOBALS['__test_posts'][9] = new WP_Post([
            'ID' => 9,
            'post_title' => 'Quran class',
            'post_excerpt' => 'Saturday.',
            'post_name' => 'quran-class',
            'post_type' => 'tribe_events',
            'post_status' => 'publish',
        ]);

        $payload = $this->build_payload(9);

        $this->assertSame('events', $payload['data']['type']);
    }
}
