<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GatewayClientTest extends TestCase {

    private array $requests = [];

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $this->requests = [];
    }

    private function transport(array $responses): Masjid_Feed_Gateway {
        $requests = &$this->requests;
        $queue = $responses;
        return new Masjid_Feed_Gateway(function (string $method, string $url, array $options) use (&$requests, &$queue) {
            $requests[] = compact('method', 'url', 'options');
            return array_shift($queue) ?? ['status' => 500, 'headers' => [], 'body' => ''];
        });
    }

    private function response(int $status, array $body, array $headers = []): array {
        return ['status' => $status, 'headers' => $headers, 'body' => json_encode($body)];
    }

    public function test_register_site_posts_challenge_and_returns_secret(): void {
        $gateway = $this->transport([
            $this->response(201, ['site_id' => 'site-1', 'url' => 'https://example.test/', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ]);

        $result = $gateway->register_site('challenge-1', 'token-1');

        $this->assertSame(['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1'], $result);
        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites', $request['url']);
        $this->assertArrayNotHasKey('Authorization', $request['options']['headers']);
        $body = json_decode($request['options']['body'], true);
        $this->assertSame('challenge-1', $body['challenge']);
        $this->assertSame('token-1', $body['fetch_token']);
        $this->assertSame('https://example.test/', $body['url']);
        $this->assertSame('Test Masjid', $body['name']);
        $this->assertSame('masjidapp://', $body['deep_link_prefix']);
    }

    public function test_register_site_includes_prayer_times_location(): void {
        $GLOBALS['__test_options']['muslprti_settings'] = ['lat' => 52.3702, 'lng' => 4.8952];
        $gateway = $this->transport([
            $this->response(201, ['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ]);

        $gateway->register_site('challenge-1', 'token-1');

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertSame(['latitude' => 52.3702, 'longitude' => 4.8952], $body['location']);
    }

    public function test_register_site_omits_location_without_prayer_times_settings(): void {
        $gateway = $this->transport([
            $this->response(201, ['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ]);

        $gateway->register_site('challenge-1', 'token-1');

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertArrayNotHasKey('location', $body);
    }

    public function test_register_site_omits_out_of_range_location(): void {
        $GLOBALS['__test_options']['muslprti_settings'] = ['lat' => 91, 'lng' => 4.8952];
        $gateway = $this->transport([
            $this->response(201, ['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ]);

        $gateway->register_site('challenge-1', 'token-1');

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertArrayNotHasKey('location', $body);
    }

    public function test_register_site_returns_pending_without_secret(): void {
        $gateway = $this->transport([
            $this->response(201, ['site_id' => 'site-1', 'status' => 'pending', 'verification_url' => 'https://example.test/wp-json/masjid/v1/gateway-verification']),
        ]);

        $result = $gateway->register_site('challenge-1', 'token-1');

        $this->assertSame(['site_id' => 'site-1', 'status' => 'pending', 'gateway_secret' => ''], $result);
    }

    public function test_register_site_rejects_incomplete_response(): void {
        $gateway = $this->transport([
            $this->response(201, ['status' => 'pending']),
        ]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->register_site('challenge-1', 'token-1');
    }

    public function test_verify_site_returns_secret_without_bearer_auth(): void {
        $gateway = $this->transport([
            $this->response(200, ['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1']),
        ]);

        $result = $gateway->verify_site('site-1', 'token-1');

        $this->assertSame(['site_id' => 'site-1', 'status' => 'active', 'gateway_secret' => 'secret-1'], $result);
        $request = $this->requests[0];
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites/site-1/verify', $request['url']);
        $this->assertArrayNotHasKey('Authorization', $request['options']['headers']);
        $this->assertSame(['fetch_token' => 'token-1'], json_decode($request['options']['body'], true));
    }

    public function test_verify_site_rejects_incomplete_response(): void {
        $gateway = $this->transport([
            $this->response(200, ['gateway_secret' => 'secret-1']),
        ]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->verify_site('site-1', 'token-1');
    }

    public function test_verify_site_maps_challenge_expiry(): void {
        $gateway = $this->transport([
            $this->response(410, ['error' => ['code' => 'challenge_expired', 'message' => 'verification challenge expired; register again']]),
        ]);

        try {
            $gateway->verify_site('site-1', 'token-1');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Exception $exception) {
            $this->assertSame(410, $exception->http_status());
            $this->assertFalse($exception->is_retryable());
        }
    }

    public function test_issue_topic_key_forwards_app_check_and_site_id(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['gateway_site_id' => 'site-1'];
        $gateway = $this->transport([
            $this->response(200, ['site_id' => 'site-1', 'topic' => 'tenant_site-1_v1_secret', 'version' => 1, 'expires_at' => '2026-11-01T00:00:00Z']),
        ]);

        $key = $gateway->issue_topic_key('app-check-token-1');

        $this->assertSame('tenant_site-1_v1_secret', $key['topic']);
        $request = $this->requests[0];
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/topic-key', $request['url']);
        $this->assertSame('app-check-token-1', $request['options']['headers']['X-Firebase-AppCheck']);
        $this->assertSame(['site_id' => 'site-1'], json_decode($request['options']['body'], true));
    }

    public function test_issue_topic_key_requires_topic_in_response(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['gateway_site_id' => 'site-1'];
        $gateway = $this->transport([
            $this->response(200, ['site_id' => 'site-1']),
        ]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->issue_topic_key('app-check-token-1');
    }

    public function test_issue_topic_key_maps_invalid_app_check(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['gateway_site_id' => 'site-1'];
        $gateway = $this->transport([
            $this->response(401, ['error' => ['code' => 'app_check', 'message' => 'invalid app check token']]),
        ]);

        try {
            $gateway->issue_topic_key('bad-token');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Authentication_Error $exception) {
            $this->assertSame('invalid app check token', $exception->getMessage());
            $this->assertSame(401, $exception->http_status());
        }
    }

    public function test_send_posts_gateway_schema(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['send_id' => 'projects/masjidfeed/messages/1', 'state' => 'accepted']),
        ]);

        $send_id = $gateway->send([
            'title' => 'Jumaat',
            'body' => '13:00',
            'data' => [
                'notificationId' => 'n-1',
                'deepLink' => 'masjidapp://open/v1/masjids/masjid-x/announcements/5',
            ],
        ]);

        $this->assertSame('projects/masjidfeed/messages/1', $send_id);
        $request = $this->requests[0];
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites/site-1/messages', $request['url']);
        $this->assertSame('Bearer secret-1', $request['options']['headers']['Authorization']);
        $body = json_decode($request['options']['body'], true);
        $this->assertSame([
            'notification_id' => 'n-1',
            'title' => 'Jumaat',
            'body' => '13:00',
            'deep_link' => 'masjidapp://open/v1/masjids/masjid-x/announcements/5',
        ], $body);
    }

    public function test_send_truncates_long_titles_within_gateway_limit(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['send_id' => 'x']),
        ]);

        $gateway->send(['title' => str_repeat('a', 150), 'body' => '', 'data' => ['notificationId' => 'n']]);

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertLessThanOrEqual(100, strlen($body['title']));
    }

    public function test_send_truncates_multibyte_bodies_within_gateway_byte_limit(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['send_id' => 'x']),
        ]);

        // 180 Arabic characters are ~360 UTF-8 bytes: over the gateway's
        // 300-byte body limit even though the character count is within the
        // excerpt used when queuing.
        $gateway->send(['title' => 't', 'body' => str_repeat('ا', 180), 'data' => ['notificationId' => 'n']]);

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertLessThanOrEqual(300, strlen($body['body']));
        // json_encode fails on malformed UTF-8: the cut must land on a
        // character boundary.
        $this->assertNotFalse(json_encode($body['body'], JSON_THROW_ON_ERROR));
        $this->assertSame('…', mb_substr($body['body'], -1));
    }

    public function test_send_keeps_short_multibyte_bodies_intact(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['send_id' => 'x']),
        ]);

        $body_text = str_repeat('ا', 150); // 300 bytes: exactly at the limit.
        $gateway->send(['title' => 't', 'body' => $body_text, 'data' => ['notificationId' => 'n']]);

        $body = json_decode($this->requests[0]['options']['body'], true);
        $this->assertSame($body_text, $body['body']);
    }

    public function test_error_code_is_captured_from_the_error_envelope(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['gateway_site_id' => 'site-1'];
        $gateway = $this->transport([
            $this->response(400, ['error' => ['code' => 'verification_failed', 'message' => 'verification fetch rejected: callback status 404']]),
            $this->response(429, ['error' => ['code' => 'daily_quota', 'message' => 'rate limited: daily_quota']]),
        ]);

        try {
            $gateway->register_site('challenge-1', 'token-1');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Invalid_Request $exception) {
            $this->assertSame('verification_failed', $exception->error_code());
            $this->assertSame(400, $exception->http_status());
        }

        try {
            $gateway->issue_topic_key('app-check-token-1');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Rate_Limited $exception) {
            $this->assertSame('daily_quota', $exception->error_code());
        }
    }

    public function test_error_code_is_empty_without_an_error_envelope(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['gateway_site_id' => 'site-1'];
        $gateway = $this->transport([
            ['status' => 502, 'headers' => [], 'body' => 'bad gateway'],
        ]);

        try {
            $gateway->issue_topic_key('app-check-token-1');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Exception $exception) {
            $this->assertSame('', $exception->error_code());
        }
    }

    public function test_validate_connection_posts_to_the_validate_endpoint(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['ok' => true]),
        ]);

        $this->assertTrue($gateway->validate_connection());

        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites/site-1/validate', $request['url']);
        $this->assertSame('Bearer secret-1', $request['options']['headers']['Authorization']);
    }

    public function test_validate_connection_maps_failures(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'stale-secret',
        ];
        $gateway = $this->transport([
            $this->response(401, ['error' => ['code' => 'unauthorized', 'message' => 'invalid gateway secret']]),
        ]);

        try {
            $gateway->validate_connection();
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Authentication_Error $exception) {
            $this->assertSame('invalid gateway secret', $exception->getMessage());
        }
    }

    public function test_validate_connection_requires_registration(): void {
        $gateway = $this->transport([]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->validate_connection();
    }

    public function test_rotate_topic_returns_the_new_version(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['site_id' => 'site-1', 'topic_version' => 2]),
        ]);

        $version = $gateway->rotate_topic();

        $this->assertSame(2, $version);
        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites/site-1/rotate', $request['url']);
        $this->assertSame('Bearer secret-1', $request['options']['headers']['Authorization']);
        $this->assertArrayNotHasKey('body', $request['options']);
    }

    public function test_rotate_topic_rejects_incomplete_response(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(200, ['site_id' => 'site-1']),
        ]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->rotate_topic();
    }

    public function test_site_audit_gets_the_audit_endpoint(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $entries = [
            ['id' => 'a1', 'actor' => 'plugin', 'action' => 'site.registered', 'site_id' => 'site-1', 'at' => '2026-11-01T00:00:00Z'],
        ];
        $gateway = $this->transport([
            $this->response(200, $entries),
        ]);

        $this->assertSame($entries, $gateway->site_audit());

        $request = $this->requests[0];
        $this->assertSame('GET', $request['method']);
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/sites/site-1/audit', $request['url']);
        $this->assertSame('Bearer secret-1', $request['options']['headers']['Authorization']);
        $this->assertArrayNotHasKey('body', $request['options']);
    }

    public function test_site_audit_maps_unknown_site(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(404, ['error' => ['code' => 'not_found', 'message' => 'site not found']]),
        ]);

        $this->expectException(Masjid_Feed_Gateway_Not_Found::class);
        $gateway->site_audit();
    }

    public function test_send_status_gets_the_message_record_preserving_slashes(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $record = ['name' => 'projects/masjidfeed/messages/abc-123', 'state' => 'accepted'];
        $gateway = $this->transport([
            $this->response(200, $record),
        ]);

        $result = $gateway->send_status('projects/masjidfeed/messages/abc-123');

        $this->assertSame($record, $result);
        $request = $this->requests[0];
        $this->assertSame('GET', $request['method']);
        // The send_id is an FCM message name containing slashes: it is part
        // of the path verbatim, not percent-encoded into one segment.
        $this->assertSame(
            'https://masjidfeed-app.gsf8.org/v1/sites/site-1/messages/projects/masjidfeed/messages/abc-123',
            $request['url']
        );
        $this->assertSame('Bearer secret-1', $request['options']['headers']['Authorization']);
    }

    public function test_send_status_maps_unknown_send(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(404, ['error' => ['code' => 'unknown_send', 'message' => 'no FCM record for this send_id']]),
        ]);

        try {
            $gateway->send_status('projects/masjidfeed/messages/gone');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Not_Found $exception) {
            $this->assertSame('unknown_send', $exception->error_code());
        }
    }

    public function test_send_status_rejects_unusable_send_ids(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([]);

        foreach (['', '  ', 'projects/x/messages/a?b', 'projects/x/messages/a#b'] as $send_id) {
            try {
                $gateway->send_status($send_id);
                $this->fail('Expected an exception.');
            } catch (Masjid_Feed_Gateway_Invalid_Request $exception) {
                // No request may leave the plugin for an unusable send_id.
                $this->assertCount(0, $this->requests);
            }
        }
    }

    public function test_send_requires_registration(): void {
        $gateway = $this->transport([]);

        $this->expectException(Masjid_Feed_Gateway_Exception::class);
        $gateway->send(['title' => 't', 'body' => '', 'data' => ['notificationId' => 'n']]);
    }

    public function test_send_maps_rate_limit_with_retry_after(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = $this->transport([
            $this->response(429, ['error' => ['code' => 'quota', 'message' => 'rate limited: quota']], ['Retry-After' => '90']),
        ]);

        try {
            $gateway->send(['title' => 't', 'body' => '', 'data' => ['notificationId' => 'n']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Rate_Limited $exception) {
            $this->assertTrue($exception->is_retryable());
            $this->assertSame(90, $exception->retry_after());
        }
    }

    public function test_connection_failure_is_retryable(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'gateway_site_id' => 'site-1',
            'gateway_secret' => 'secret-1',
        ];
        $gateway = new Masjid_Feed_Gateway(function () {
            throw new RuntimeException('cURL error 28');
        });

        try {
            $gateway->send(['title' => 't', 'body' => '', 'data' => ['notificationId' => 'n']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Gateway_Exception $exception) {
            $this->assertInstanceOf(Masjid_Feed_Gateway_Connection_Failed::class, $exception);
            $this->assertTrue($exception->is_retryable());
        }
    }
}
