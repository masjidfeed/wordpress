<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PushMetaBoxTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__test_options'] = [];
    }

    private function render(WP_Post $post): string {
        ob_start();
        (new Masjid_Feed_Push_Notifications())->render_meta_box($post);
        return (string) ob_get_clean();
    }

    public function test_send_button_is_active_when_push_notifications_are_enabled(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'masjidfeed'];

        $html = $this->render(new WP_Post(['ID' => 5, 'post_status' => 'publish']));

        $this->assertStringContainsString('masjidfeed_resend_push', $html);
        $this->assertStringContainsString('Send notification', $html);
        $this->assertStringNotContainsString('<button type="button" class="button" disabled>', $html);
        // No trigger tag requirement: the tag hint is gone.
        $this->assertStringNotContainsString('Assign the configured push tag', $html);
    }

    public function test_send_button_is_disabled_with_a_settings_link_when_push_is_disabled(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'disabled'];

        $html = $this->render(new WP_Post(['ID' => 5, 'post_status' => 'publish']));

        $this->assertStringContainsString('<button type="button" class="button" disabled>', $html);
        $this->assertStringContainsString('Push notifications are disabled', $html);
        $this->assertStringContainsString(
            'options-general.php?page=masjidfeed-app-settings&tab=push',
            $html
        );
        // The dead resend action must not be reachable.
        $this->assertStringNotContainsString('masjidfeed_resend_push', $html);
    }

    public function test_unpublished_post_is_asked_to_publish_first(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'masjidfeed'];

        $html = $this->render(new WP_Post(['ID' => 5, 'post_status' => 'draft']));

        $this->assertStringContainsString('Publish this post before sending a notification.', $html);
        $this->assertStringNotContainsString('masjidfeed_resend_push', $html);
    }

    public function test_eligibility_depends_only_on_push_notifications_being_enabled(): void {
        $notifications = new Masjid_Feed_Push_Notifications();
        $method = new ReflectionMethod(Masjid_Feed_Push_Notifications::class, 'post_is_eligible');

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'masjidfeed', 'push_tag' => 123];
        // No tags assigned to the post: eligibility no longer depends on them.
        $GLOBALS['__test_terms']['post_tag:5'] = [];
        $this->assertTrue($method->invoke($notifications));

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'disabled'];
        $this->assertFalse($method->invoke($notifications));
    }
}
