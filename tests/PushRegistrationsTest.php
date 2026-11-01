<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PushRegistrationsTest extends TestCase {

    private array $gateway_requests = [];

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_transients'] = [];
        $GLOBALS['__test_wp_remote_requests'] = [];
        $GLOBALS['__test_wp_remote_request_responses'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $this->gateway_requests = [];
    }

    private function store_settings(array $settings): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = array_merge(['masjid_id' => 'masjid-x'], $settings);
    }

    private function store_gateway(array $registration): void {
        $GLOBALS['__test_options'][MASJIDFEED_GATEWAY_OPTION_KEY] = $registration;
    }

    private function rest_request(string $method): WP_REST_Request {
        $request = new WP_REST_Request($method, '/masjid/v1/push/registrations');
        $request->set_param('masjidId', 'masjid-x');
        $request->set_param('platform', 'ios');
        $request->set_header('x-firebase-appcheck', 'app-check-token');
        return $request;
    }

    private function api(Masjid_Feed_Gateway $gateway): Masjid_Feed_REST_API {
        $api = new Masjid_Feed_REST_API();
        $property = new ReflectionProperty(Masjid_Feed_REST_API::class, 'gateway_client');
        $property->setValue($api, $gateway);
        return $api;
    }

    private function gateway_transport(array $responses): Masjid_Feed_Gateway {
        $requests = &$this->gateway_requests;
        $queue = $responses;
        return new Masjid_Feed_Gateway(function (string $method, string $url, array $options) use (&$requests, &$queue) {
            $requests[] = compact('method', 'url', 'options');
            return array_shift($queue) ?? ['status' => 500, 'headers' => [], 'body' => ''];
        });
    }

    public function test_masjidfeed_mode_proxies_registration_to_topic_key(): void {
        $this->store_settings([
            'push_mode' => 'masjidfeed',
            'push_enabled' => 1,
        ]);
        $this->store_gateway(['site_id' => 'site-1']);
        $api = $this->api($this->gateway_transport([
            ['status' => 200, 'headers' => [], 'body' => json_encode(['site_id' => 'site-1', 'topic' => 'tenant_site-1_v1_secret', 'version' => 1, 'expires_at' => '2026-11-01T00:00:00Z'])],
        ]));

        $response = $api->register_push_token($this->rest_request('POST'));

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['registered']);
        $this->assertSame('tenant_site-1_v1_secret', $data['topicKey']['topic']);
        $this->assertArrayNotHasKey('result', $data);
        $request = $this->gateway_requests[0];
        $this->assertSame('https://masjidfeed-app.gsf8.org/v1/topic-key', $request['url']);
        $this->assertSame('app-check-token', $request['options']['headers']['X-Firebase-AppCheck']);
        $this->assertSame(['site_id' => 'site-1'], json_decode($request['options']['body'], true));
    }

    public function test_masjidfeed_mode_maps_topic_key_failures(): void {
        $this->store_settings([
            'push_mode' => 'masjidfeed',
            'push_enabled' => 1,
        ]);
        $this->store_gateway(['site_id' => 'site-1']);
        $api = $this->api($this->gateway_transport([
            ['status' => 401, 'headers' => [], 'body' => json_encode(['error' => ['code' => 'app_check', 'message' => 'invalid app check token']])],
        ]));

        $response = $api->register_push_token($this->rest_request('POST'));

        $this->assertTrue(is_wp_error($response));
        $this->assertSame('invalid app check token', $response->get_error_message());
    }

    public function test_masjidfeed_mode_authorization_skips_local_firebase(): void {
        // No FCM configuration is stored: a local App Check verification
        // would fail, so MasjidFeed mode must not attempt it.
        $this->store_settings(['push_mode' => 'masjidfeed', 'push_enabled' => 1]);
        $api = new Masjid_Feed_REST_API();

        $this->assertTrue($api->authorize_push_registration($this->rest_request('POST')));
    }

    public function test_masjidfeed_mode_unregistration_needs_no_firebase(): void {
        $this->store_settings(['push_mode' => 'masjidfeed', 'push_enabled' => 1]);
        $api = new Masjid_Feed_REST_API();

        $response = $api->unregister_push_token($this->rest_request('DELETE'));

        $this->assertSame(array('registered' => false), $response->get_data());
    }

    public function test_fcm_mode_authorization_still_requires_firebase_config(): void {
        $this->store_settings(['push_mode' => 'fcm', 'push_enabled' => 1]);
        $api = new Masjid_Feed_REST_API();

        $response = $api->authorize_push_registration($this->rest_request('POST'));

        $this->assertTrue(is_wp_error($response));
        $this->assertSame('Firebase mobile application configuration is incomplete.', $response->get_error_message());
    }

    public function test_both_mode_merges_topic_key_and_fcm_results(): void {
        $this->store_settings([
            'push_mode' => 'both',
            'push_enabled' => 1,
        ]);
        $this->store_gateway(['site_id' => 'site-1']);
        $api = $this->api($this->gateway_transport([
            ['status' => 200, 'headers' => [], 'body' => json_encode(['site_id' => 'site-1', 'topic' => 'tenant_site-1_v1_secret', 'version' => 1, 'expires_at' => '2026-11-01T00:00:00Z'])],
        ]));
        $fcm = new class extends Masjid_Feed_Firebase {
            public function __construct() {
            }

            public function subscribe($token) {
                return array('errors' => array());
            }
        };
        $property = new ReflectionProperty(Masjid_Feed_REST_API::class, 'authorized_firebase');
        $property->setValue($api, $fcm);
        $request = $this->rest_request('POST');
        $request->set_param('token', 'current-fcm-token-1234567890');

        $response = $api->register_push_token($request);

        $data = $response->get_data();
        $this->assertTrue($data['registered']);
        $this->assertSame('tenant_site-1_v1_secret', $data['topicKey']['topic']);
        $this->assertSame(array('successful' => true, 'errorCount' => 0), $data['result']);
    }

    public function test_both_mode_fcm_token_is_required_for_the_fcm_half(): void {
        $this->store_settings([
            'push_mode' => 'both',
            'push_enabled' => 1,
        ]);
        $this->store_gateway(['site_id' => 'site-1']);
        $api = $this->api($this->gateway_transport([
            ['status' => 200, 'headers' => [], 'body' => json_encode(['site_id' => 'site-1', 'topic' => 'tenant_site-1_v1_secret', 'version' => 1, 'expires_at' => '2026-11-01T00:00:00Z'])],
        ]));
        $fcm = new class extends Masjid_Feed_Firebase {
            public function __construct() {
            }

            public function subscribe($token) {
                throw new RuntimeException('should not be called');
            }
        };
        $property = new ReflectionProperty(Masjid_Feed_REST_API::class, 'authorized_firebase');
        $property->setValue($api, $fcm);
        $request = $this->rest_request('POST');
        $request->set_param('token', '');

        $response = $api->register_push_token($request);

        $data = $response->get_data();
        $this->assertTrue($data['registered']);
        $this->assertArrayHasKey('topicKey', $data);
        $this->assertArrayNotHasKey('result', $data);
    }
}
