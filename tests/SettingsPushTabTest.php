<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SettingsPushTabTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_settings_errors'] = [];
        $GLOBALS['__test_wp_remote_requests'] = [];
        $GLOBALS['__test_wp_remote_request_responses'] = [];
        $GLOBALS['__test_password_counter'] = 0;
        $GLOBALS['__test_submit_buttons'] = [];
    }

    private function render_push_tab(): string {
        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_push_tab');
        $method->invoke($settings, Masjid_Feed_Settings::get_all_options(), []);
        return (string) ob_get_clean();
    }

    private function store_settings(array $settings): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = $settings;
    }

    public function test_push_tab_renders_mode_dropdown_and_registration_form(): void {
        $html = $this->render_push_tab();

        $this->assertStringContainsString('masjidfeed_push_mode', $html);
        foreach (array('disabled', 'masjidfeed', 'fcm', 'both') as $mode) {
            $this->assertStringContainsString('value="' . $mode . '"', $html);
        }
        $this->assertStringContainsString('masjidfeed_register_gateway', $html);
        // The old enable checkbox is replaced by the dropdown.
        $this->assertStringNotContainsString('[push_enabled]"', $html);
    }

    public function test_push_tab_collapses_fcm_section_and_holds_uploads_and_instructions(): void {
        $this->store_settings(['push_mode' => 'masjidfeed']);

        $html = $this->render_push_tab();

        $this->assertStringContainsString('masjidfeed_fcm_setup', $html);
        $this->assertStringNotContainsString('" open>', $html);
        foreach (array('masjidfeed_service_account', 'masjidfeed_ios_config', 'masjidfeed_android_config', 'Required files and server configuration', 'Server encryption key', 'Enable Firebase App Check') as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_push_tab_expands_fcm_section_for_fcm_mode(): void {
        $this->store_settings(['push_mode' => 'fcm']);

        $this->assertStringContainsString('" open>', $this->render_push_tab());
    }

    public function test_push_tab_expands_fcm_section_for_both_mode(): void {
        $this->store_settings(['push_mode' => 'both']);

        $this->assertStringContainsString('" open>', $this->render_push_tab());
    }

    public function test_push_tab_shows_registration_status(): void {
        $html = $this->render_push_tab();
        $this->assertStringContainsString('Not registered.', $html);

        $this->store_settings(['gateway_site_id' => 'site-1', 'gateway_secret' => 'secret-1']);
        $html = $this->render_push_tab();
        $this->assertStringContainsString('Registered.', $html);
        $this->assertStringContainsString('site-1', $html);
    }

    public function test_push_tab_shows_pending_registration_with_validate_button(): void {
        // A pending registration has a site ID but no secret yet: the gateway
        // issued the ID but the one-time secret was never collected.
        $this->store_settings(['gateway_site_id' => 'site-9']);

        $html = $this->render_push_tab();

        $this->assertStringContainsString('Pending.', $html);
        $this->assertStringContainsString('site-9', $html);
        $this->assertStringNotContainsString('Not registered.', $html);
        // The rotation form is only rendered for completed registrations.
        $this->assertStringNotContainsString('masjidfeed_rotate_gateway_topic', $html);
        // The pending button retries verification (POST
        // /v1/sites/{site_id}/verify with the stored fetch token) instead of
        // registering again, and the description explains the retry.
        $this->assertSame('Validate', $GLOBALS['__test_submit_buttons'][0]['text']);
        $this->assertStringContainsString('gateway-verification', $html);
        $this->assertStringContainsString('24 hours', $html);
    }

    public function test_push_tab_shows_register_button_when_not_pending(): void {
        $this->render_push_tab();

        $this->assertSame('Register with MasjidFeed', $GLOBALS['__test_submit_buttons'][0]['text']);

        $this->store_settings(['gateway_site_id' => 'site-1', 'gateway_secret' => 'secret-1']);
        $GLOBALS['__test_submit_buttons'] = [];
        $this->render_push_tab();

        // The registration button keeps its label once registered; the
        // rotate button follows it.
        $this->assertSame('Register with MasjidFeed', $GLOBALS['__test_submit_buttons'][0]['text']);
        $this->assertSame('Rotate topic keys', $GLOBALS['__test_submit_buttons'][1]['text']);
    }

    public function test_gateway_pending_reflects_stored_state(): void {
        $this->assertFalse(Masjid_Feed_Settings::is_gateway_pending());

        $this->store_settings(['gateway_site_id' => 'site-9']);
        $this->assertTrue(Masjid_Feed_Settings::is_gateway_pending());

        $this->store_settings(['gateway_site_id' => 'site-9', 'gateway_secret' => 'secret-1']);
        $this->assertFalse(Masjid_Feed_Settings::is_gateway_pending());
        $this->assertTrue(Masjid_Feed_Settings::is_gateway_registered());
    }

    public function test_push_mode_defaults_to_masjidfeed_for_fresh_installs(): void {
        $this->assertSame('masjidfeed', Masjid_Feed_Settings::push_mode());
    }

    public function test_push_mode_derives_from_legacy_checkbox(): void {
        $this->store_settings(['push_enabled' => 1, 'firebase_project_id' => 'proj']);
        $this->assertSame('fcm', Masjid_Feed_Settings::push_mode());

        $this->store_settings(['push_enabled' => 0]);
        $this->assertSame('disabled', Masjid_Feed_Settings::push_mode());
    }

    public function test_push_mode_reads_stored_mode(): void {
        $this->store_settings(['push_mode' => 'both', 'push_enabled' => 1]);
        $this->assertSame('both', Masjid_Feed_Settings::push_mode());
    }

    private function sanitize(array $input, array $existing = []): array {
        if ($existing) {
            $this->store_settings($existing);
        }
        $settings = new Masjid_Feed_Settings();
        return $settings->sanitize_settings(array_merge(['_tab' => 'push'], $input));
    }

    public function test_sanitize_stores_mode_and_derives_push_enabled(): void {
        $out = $this->sanitize(['push_mode' => 'both', 'push_tag' => 0]);

        $this->assertSame('both', $out['push_mode']);
        $this->assertSame(1, $out['push_enabled']);

        $out = $this->sanitize(['push_mode' => 'disabled', 'push_tag' => 0]);

        $this->assertSame('disabled', $out['push_mode']);
        $this->assertSame(0, $out['push_enabled']);
    }

    public function test_sanitize_falls_back_to_effective_mode_for_unknown_values(): void {
        $out = $this->sanitize(['push_mode' => 'bogus', 'push_tag' => 0], ['push_enabled' => 1]);

        $this->assertSame('fcm', $out['push_mode']);
        $this->assertSame(1, $out['push_enabled']);
    }

    public function test_sanitize_keeps_gateway_registration(): void {
        $out = $this->sanitize(
            ['push_mode' => 'masjidfeed', 'push_tag' => 0],
            ['gateway_site_id' => 'site-1', 'gateway_secret' => 'secret-1']
        );

        $this->assertSame('site-1', $out['gateway_site_id']);
        $this->assertSame('secret-1', $out['gateway_secret']);
    }

    private function register_with_gateway(): array {
        $settings = new Masjid_Feed_Settings();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'register_with_gateway');
        return $method->invoke($settings);
    }

    private function http_response(int $status, array $body): array {
        return [
            'response' => ['code' => $status],
            'headers' => [],
            'body' => json_encode($body),
        ];
    }

    public function test_registration_publishes_challenge_and_stores_secret(): void {
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(201, ['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ];

        $result = $this->register_with_gateway();

        $this->assertSame('active', $result['state']);
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-1', $stored['gateway_site_id']);
        $this->assertSame('secret-1', $stored['gateway_secret']);
        // The proof challenge and fetch token are consumed once verification
        // completes.
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION, $GLOBALS['__test_options']);
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION, $GLOBALS['__test_options']);
        // The challenge and fetch token were stored before the registration
        // POST and the token submitted in its body.
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites', $GLOBALS['__test_wp_remote_requests'][0]['url']);
        $body = json_decode($GLOBALS['__test_wp_remote_requests'][0]['args']['body'], true);
        $this->assertSame('test-challenge', $body['challenge']);
        $this->assertSame('test-challenge-2', $body['fetch_token']);
        $this->assertArrayNotHasKey('Authorization', $GLOBALS['__test_wp_remote_requests'][0]['args']['headers']);
    }

    public function test_registration_keeps_challenge_when_callback_fails(): void {
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(201, ['site_id' => 'site-9', 'status' => 'pending']),
        ];

        $result = $this->register_with_gateway();

        $this->assertSame('pending', $result['state']);
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-9', $stored['gateway_site_id']);
        $this->assertArrayNotHasKey('gateway_secret', $stored);
        // The challenge and fetch token stay stored so the retry path can
        // verify.
        $this->assertSame('test-challenge', $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION]);
        $this->assertSame('test-challenge-2', $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION]);
    }

    public function test_registration_completes_pending_site_with_verify(): void {
        $this->store_settings(['gateway_site_id' => 'site-9']);
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'pending-challenge';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'pending-token';
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(200, ['site_id' => 'site-9', 'status' => 'active', 'gateway_secret' => 'secret-2']),
        ];

        $result = $this->register_with_gateway();

        $this->assertSame('active', $result['state']);
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-9', $stored['gateway_site_id']);
        $this->assertSame('secret-2', $stored['gateway_secret']);
        $this->assertSame(
            'https://masjidfeed-app.gsf8.org/v1/sites/site-9/verify',
            $GLOBALS['__test_wp_remote_requests'][0]['url']
        );
        $this->assertSame(
            ['fetch_token' => 'pending-token'],
            json_decode($GLOBALS['__test_wp_remote_requests'][0]['args']['body'], true)
        );
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION, $GLOBALS['__test_options']);
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION, $GLOBALS['__test_options']);
    }

    public function test_registration_starts_over_when_pending_challenge_expired(): void {
        $this->store_settings(['gateway_site_id' => 'site-9']);
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'pending-challenge';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'pending-token';
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(410, ['error' => ['code' => 'challenge_expired', 'message' => 'verification challenge expired; register again']]),
            $this->http_response(201, ['site_id' => 'site-new', 'status' => 'active', 'gateway_secret' => 'secret-3']),
        ];

        $result = $this->register_with_gateway();

        $this->assertSame('active', $result['state']);
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-new', $stored['gateway_site_id']);
        $this->assertSame('secret-3', $stored['gateway_secret']);
        $this->assertCount(2, $GLOBALS['__test_wp_remote_requests']);
        $this->assertStringEndsWith('/v1/sites/site-9/verify', $GLOBALS['__test_wp_remote_requests'][0]['url']);
        $this->assertStringEndsWith('/v1/sites', $GLOBALS['__test_wp_remote_requests'][1]['url']);
        // The restart consumed the old challenge/token pair and stored a
        // fresh one.
        $restart = json_decode($GLOBALS['__test_wp_remote_requests'][1]['args']['body'], true);
        $this->assertSame('test-challenge', $restart['challenge']);
        $this->assertSame('test-challenge-2', $restart['fetch_token']);
    }

    public function test_registration_failure_keeps_pending_state_for_retry(): void {
        $this->store_settings(['gateway_site_id' => 'site-9']);
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'pending-challenge';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'pending-token';
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(400, ['error' => ['code' => 'verification_failed', 'message' => 'domain verification callback failed; serve the challenge to the gateway\'s fetch and register again']]),
        ];

        try {
            $this->register_with_gateway();
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Exception $exception) {
            $this->assertSame(400, $exception->http_status());
        }
        // Nothing was overwritten: the pending site and its challenge/token
        // stay in place so the next attempt retries verification.
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-9', $stored['gateway_site_id']);
        $this->assertArrayNotHasKey('gateway_secret', $stored);
        $this->assertSame('pending-challenge', $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION]);
        $this->assertSame('pending-token', $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION]);
    }

    public function test_registration_starts_over_when_pending_site_was_torn_down(): void {
        // A definitive verification rejection tears the pending site down at
        // the gateway, so the next verify attempt answers 404 and the plugin
        // must start a fresh registration instead of retrying a dead site.
        $this->store_settings(['gateway_site_id' => 'site-9']);
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'pending-challenge';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'pending-token';
        $GLOBALS['__test_wp_remote_request_responses'] = [
            $this->http_response(404, ['error' => ['code' => 'not_found', 'message' => 'site not found']]),
            $this->http_response(201, ['site_id' => 'site-new', 'status' => 'active', 'gateway_secret' => 'secret-4']),
        ];

        $result = $this->register_with_gateway();

        $this->assertSame('active', $result['state']);
        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame('site-new', $stored['gateway_site_id']);
        $this->assertSame('secret-4', $stored['gateway_secret']);
        $this->assertCount(2, $GLOBALS['__test_wp_remote_requests']);
        $this->assertStringEndsWith('/v1/sites/site-9/verify', $GLOBALS['__test_wp_remote_requests'][0]['url']);
        $this->assertStringEndsWith('/v1/sites', $GLOBALS['__test_wp_remote_requests'][1]['url']);
        // A fresh challenge and fetch token were generated for the new
        // registration.
        $restart = json_decode($GLOBALS['__test_wp_remote_requests'][1]['args']['body'], true);
        $this->assertSame('test-challenge', $restart['challenge']);
        $this->assertSame('test-challenge-2', $restart['fetch_token']);
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION, $GLOBALS['__test_options']);
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION, $GLOBALS['__test_options']);
    }

    public function test_url_claimed_pending_error_maps_to_actionable_notice(): void {
        // A gateway 5xx after the pending site was committed leaves the site
        // ID unknown to the plugin; the next registration then answers 400
        // validation "url already registered and pending verification". The
        // notice explains how the claim frees itself instead of echoing the
        // raw gateway message.
        $gateway_message = 'url already registered and pending verification; complete it via POST /v1/sites/{site_id}/verify or email masjidfeed@goodsoftware.foundation to have it revoked';
        $exception = new Masjid_Feed_Gateway_Invalid_Request($gateway_message);
        $exception->set_response_details(400, json_encode(['error' => ['code' => 'validation', 'message' => $gateway_message]]));

        $settings = new Masjid_Feed_Settings();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'gateway_registration_error_message');

        $mapped = $method->invoke($settings, $exception);
        $this->assertStringContainsString('24 hours', $mapped);
        $this->assertStringContainsString('masjidfeed@goodsoftware.foundation', $mapped);
        $this->assertStringNotContainsString($gateway_message, $mapped);

        // Any other gateway error is surfaced verbatim.
        $other_message = 'challenge is required: publish a one-time proof value';
        $other = new Masjid_Feed_Gateway_Invalid_Request($other_message);
        $other->set_response_details(400, json_encode(['error' => ['code' => 'validation', 'message' => $other_message]]));
        $this->assertSame($other_message, $method->invoke($settings, $other));
    }

    public function test_push_tab_renders_topic_rotation_only_when_registered(): void {
        $this->assertStringNotContainsString('masjidfeed_rotate_gateway_topic', $this->render_push_tab());

        $this->store_settings(['gateway_site_id' => 'site-1', 'gateway_secret' => 'secret-1']);
        $html = $this->render_push_tab();
        $this->assertStringContainsString('masjidfeed_rotate_gateway_topic', $html);
        // submit_button() is a no-op in the harness, so assert the helper
        // description that explains the rotation behavior.
        $this->assertStringContainsString('grace window', $html);
    }

    private function sanitize_deep_link(string $value, string $previous = ''): array {
        return $this->sanitize(['push_mode' => 'masjidfeed', 'push_tag' => 0, 'push_deep_link_template' => $value], $previous ? ['push_deep_link_template' => $previous] : []);
    }

    public function test_sanitize_accepts_masjidapp_deep_link_templates(): void {
        $out = $this->sanitize_deep_link('masjidapp://open/v1/masjids/{masjidId}/events/{postId}');

        $this->assertSame('masjidapp://open/v1/masjids/{masjidId}/events/{postId}', $out['push_deep_link_template']);
        $this->assertSame([], $GLOBALS['__test_settings_errors']);
    }

    public function test_sanitize_rejects_deep_link_templates_outside_the_registered_prefix(): void {
        // The gateway rejects deep links that do not start with the
        // registered prefix (masjidapp://), so other URI schemes are refused
        // at save time and the previous template is kept.
        $out = $this->sanitize_deep_link('https://example.org/open/{postId}', 'masjidapp://open/{postId}');

        $this->assertSame('masjidapp://open/{postId}', $out['push_deep_link_template']);
        $this->assertSame('invalid_deep_link', $GLOBALS['__test_settings_errors'][0]['code']);

        // Without a previously stored template nothing is saved.
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_settings_errors'] = [];
        $out = $this->sanitize_deep_link('otherscheme://open/{postId}');

        $this->assertSame('', $out['push_deep_link_template']);
        $this->assertCount(1, $GLOBALS['__test_settings_errors']);
    }

    private function render_gateway_tools(): string {
        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_gateway_tools');
        $method->invoke($settings);
        return (string) ob_get_clean();
    }

    public function test_gateway_tools_render_validate_button(): void {
        $html = $this->render_gateway_tools();

        $this->assertStringContainsString('masjidfeed_validate_gateway', $html);
        // submit_button() is a no-op in the harness, so assert the helper
        // description that explains the validation.
        $this->assertStringContainsString('hosted push service', $html);
    }

    public function test_gateway_tools_render_fetched_audit_entries(): void {
        $GLOBALS['__test_transients'][Masjid_Feed_Settings::GATEWAY_AUDIT_TRANSIENT] = [
            ['id' => 'a1', 'actor' => 'plugin', 'action' => 'site.registered', 'site_id' => 'site-1', 'at' => '2026-11-01T00:00:00Z'],
        ];

        $html = $this->render_gateway_tools();

        $this->assertStringContainsString('masjidfeed_fetch_gateway_audit', $html);
        $this->assertStringContainsString('site.registered', $html);
        // The transient is consumed on display.
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_AUDIT_TRANSIENT, $GLOBALS['__test_transients']);
    }

    public function test_gateway_tools_render_fetched_send_status(): void {
        $GLOBALS['__test_transients'][Masjid_Feed_Settings::GATEWAY_SEND_STATUS_TRANSIENT] = [
            'send_id' => 'projects/masjidfeed/messages/abc-123',
            'record' => ['name' => 'projects/masjidfeed/messages/abc-123', 'state' => 'accepted'],
        ];

        $html = $this->render_gateway_tools();

        $this->assertStringContainsString('Send status', $html);
        $this->assertStringContainsString('projects/masjidfeed/messages/abc-123', $html);
        $this->assertArrayNotHasKey(Masjid_Feed_Settings::GATEWAY_SEND_STATUS_TRANSIENT, $GLOBALS['__test_transients']);
    }
}
