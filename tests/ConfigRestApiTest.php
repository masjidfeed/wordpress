<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ConfigRestApiTest extends TestCase {

    private Masjid_Feed_REST_API $api;

    protected function setUp(): void {
        parent::setUp();
        $this->api = new Masjid_Feed_REST_API();
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_transients'] = [];
    }

    private function flags(): array {
        // Saving settings invalidates the cached config; each read here is a
        // fresh one.
        $GLOBALS['__test_transients'] = [];
        $response = $this->api->get_config(new WP_REST_Request('GET', '/masjid/v1/config'));
        $data = $response->get_data();
        return $data['featureFlags'];
    }

    public function test_push_notifications_flag_follows_the_selected_mode(): void {
        // Fresh installs default to Disabled.
        $this->assertFalse($this->flags()['pushNotifications']);

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'masjidfeed'];
        $this->assertTrue($this->flags()['pushNotifications']);

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['push_mode' => 'disabled'];
        $this->assertFalse($this->flags()['pushNotifications']);
    }

    public function test_donations_flag_is_true_only_when_the_donation_url_is_set(): void {
        $this->assertFalse($this->flags()['donations']);

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['donation_url' => 'https://example.test/donate/'];
        $this->assertTrue($this->flags()['donations']);
    }

    public function test_ramadan_flag_is_true_only_when_the_ramadan_url_is_set(): void {
        $this->assertFalse($this->flags()['ramadan']);

        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = ['ramadan_url' => 'https://example.test/ramadan/'];
        $this->assertTrue($this->flags()['ramadan']);
    }

    public function test_parking_flag_is_true_only_when_a_document_is_saved(): void {
        $this->assertFalse($this->flags()['parking']);

        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = '{"center":{"latitude":1,"longitude":2},"zones":[],"events":[]}';
        $this->assertTrue($this->flags()['parking']);
    }
}
