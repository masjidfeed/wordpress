<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ParkingRestApiTest extends TestCase {

    private const ROUTE = '/masjid/v1/parking-config';

    private Masjid_Feed_REST_API $api;

    public static function setUpBeforeClass(): void {
        $raw = file_get_contents(__DIR__ . '/parking-config.json');
        if (false === $raw) {
            throw new RuntimeException('tests/parking-config.json could not be read.');
        }
        self::$fixture_raw = $raw;
        self::$fixture_document = json_decode($raw, true);
    }

    private static string $fixture_raw;
    private static array $fixture_document;

    private array $previous_options;
    private array $previous_transients;

    protected function setUp(): void {
        parent::setUp();
        $this->api = new Masjid_Feed_REST_API();
        $this->previous_options = $GLOBALS['__test_options'];
        $this->previous_transients = $GLOBALS['__test_transients'];
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_transients'] = [];
    }

    protected function tearDown(): void {
        $GLOBALS['__test_options'] = $this->previous_options;
        $GLOBALS['__test_transients'] = $this->previous_transients;
        parent::tearDown();
    }

    private function request(): WP_REST_Request {
        return new WP_REST_Request('GET', self::ROUTE);
    }

    private function save_fixture(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = self::$fixture_raw;
    }

    public function test_returns_404_when_nothing_is_saved(): void {
        $response = $this->api->get_parking_config($this->request());
        $this->assertTrue(is_wp_error($response));
        $this->assertStringContainsString('parking', $response->get_error_message());
    }

    public function test_returns_404_when_saved_value_is_undecodable(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = '{ broken';
        $response = $this->api->get_parking_config($this->request());
        $this->assertTrue(is_wp_error($response));
    }

    public function test_returns_saved_document_with_cache_headers(): void {
        $this->save_fixture();

        $response = $this->api->get_parking_config($this->request());

        $this->assertFalse(is_wp_error($response));
        $this->assertSame(200, $response->get_status());
        $this->assertSame(self::$fixture_document, $response->get_data());
        $headers = $response->get_headers();
        $this->assertArrayHasKey('ETag', $headers);
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertSame('public, max-age=60, s-maxage=3600', $headers['Cache-Control']);
        $this->assertNotEmpty($GLOBALS['__test_transients'][Masjid_Feed_REST_API::PARKING_CACHE_KEY]);
    }

    public function test_matching_if_none_match_returns_304(): void {
        $this->save_fixture();

        $first = $this->api->get_parking_config($this->request());
        $etag = $first->get_headers()['ETag'];

        $request = $this->request();
        $request->set_header('If-None-Match', $etag);
        $second = $this->api->get_parking_config($request);

        $this->assertSame(304, $second->get_status());
        $this->assertNull($second->get_data());
        $this->assertSame($etag, $second->get_headers()['ETag']);
    }

    public function test_updated_option_invalidates_cached_response(): void {
        $this->save_fixture();
        $this->api->get_parking_config($this->request());
        $this->assertNotEmpty($GLOBALS['__test_transients'][Masjid_Feed_REST_API::PARKING_CACHE_KEY]);

        $this->api->invalidate_parking_config_cache();

        $this->assertArrayNotHasKey(Masjid_Feed_REST_API::PARKING_CACHE_KEY, $GLOBALS['__test_transients']);
    }

    public function test_config_advertises_parking_url(): void {
        $response = $this->api->get_config($this->request());
        $data = $response->get_data();
        $this->assertSame(
            'https://example.test/wp-json/masjid/v1/parking-config',
            $data['endpoints']['parkingUrl']
        );
    }
}