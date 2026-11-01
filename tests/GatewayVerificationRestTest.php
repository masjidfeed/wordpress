<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class GatewayVerificationRestTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
    }

    private function request(string $token = ''): WP_REST_Request {
        $request = new WP_REST_Request('GET', '/masjid/v1/gateway-verification');
        if ('' !== $token) {
            $request->set_header('X-MasjidFeed-Fetch-Token', $token);
        }
        return $request;
    }

    public function test_returns_404_without_pending_challenge(): void {
        $api = new Masjid_Feed_REST_API();

        $response = $api->get_gateway_verification($this->request());

        $this->assertTrue(is_wp_error($response));
        $this->assertSame('No push service registration is in progress for this site.', $response->get_error_message());
    }

    public function test_serves_challenge_while_registration_is_pending(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'challenge-1';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'token-1';
        $api = new Masjid_Feed_REST_API();

        $response = $api->get_gateway_verification($this->request('token-1'));

        $this->assertSame(['challenge' => 'challenge-1'], $response);
    }

    public function test_refuses_callbacks_without_the_fetch_token(): void {
        // The challenge is never publicly readable (gateway audit A2): a
        // callback without the registrant's fetch token is refused, so a
        // squatting registration is rejected on its own callback.
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'challenge-1';
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_FETCH_TOKEN_OPTION] = 'token-1';
        $api = new Masjid_Feed_REST_API();

        $no_header = $api->get_gateway_verification($this->request());
        $wrong_token = $api->get_gateway_verification($this->request('other-token'));

        foreach ([$no_header, $wrong_token] as $response) {
            $this->assertTrue(is_wp_error($response));
            $this->assertStringContainsString('fetch token', $response->get_error_message());
        }
    }

    public function test_returns_404_when_challenge_exists_without_token(): void {
        // A challenge without a fetch token cannot be a live registration.
        $GLOBALS['__test_options'][Masjid_Feed_Settings::GATEWAY_CHALLENGE_OPTION] = 'challenge-1';
        $api = new Masjid_Feed_REST_API();

        $response = $api->get_gateway_verification($this->request('token-1'));

        $this->assertTrue(is_wp_error($response));
        $this->assertSame('No push service registration is in progress for this site.', $response->get_error_message());
    }
}
