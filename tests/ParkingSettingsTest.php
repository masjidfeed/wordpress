<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ParkingSettingsTest extends TestCase {

    private array $previous_post;
    private array $previous_options;

    protected function setUp(): void {
        parent::setUp();
        $this->previous_post = $_POST;
        $this->previous_options = $GLOBALS['__test_options'];
        $GLOBALS['__test_settings_errors'] = [];
        $GLOBALS['__test_options'] = [];
    }

    protected function tearDown(): void {
        $_POST = $this->previous_post;
        $GLOBALS['__test_options'] = $this->previous_options;
        parent::tearDown();
    }

    private static function fixture_raw(): string {
        $raw = file_get_contents(__DIR__ . '/parking-config.json');
        if (false === $raw) {
            throw new RuntimeException('tests/parking-config.json could not be read.');
        }
        return $raw;
    }

    private function sanitize(string $value): string {
        $settings = new Masjid_Feed_Settings();
        return $settings->sanitize_parking_config($value);
    }

    public function test_accepts_valid_document(): void {
        $raw = trim(self::fixture_raw());
        $this->assertSame($raw, $this->sanitize(self::fixture_raw()));
        $this->assertSame([], $GLOBALS['__test_settings_errors']);
    }

    public function test_invalid_json_is_refused_and_previous_is_kept(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = 'PREVIOUS';

        $result = $this->sanitize('{ not json');

        $this->assertSame('PREVIOUS', $result);
        $this->assertNotEmpty($GLOBALS['__test_settings_errors']);
        $this->assertSame(Masjid_Feed_Parking_Config::OPTION_KEY, $GLOBALS['__test_settings_errors'][0]['setting']);
    }

    public function test_invalid_document_is_refused_and_previous_is_kept(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = 'PREVIOUS';
        $document = json_decode(self::fixture_raw(), true);
        unset($document['events'][0]['schedule']);

        $result = $this->sanitize((string) json_encode($document, JSON_UNESCAPED_SLASHES));

        $this->assertSame('PREVIOUS', $result);
        $this->assertNotEmpty($GLOBALS['__test_settings_errors']);
    }

    public function test_empty_submission_keeps_previous(): void {
        $GLOBALS['__test_options'][Masjid_Feed_Parking_Config::OPTION_KEY] = 'PREVIOUS';

        $this->assertSame('PREVIOUS', $this->sanitize(''));
        $this->assertSame('PREVIOUS', $this->sanitize('   '));
        $this->assertSame([], $GLOBALS['__test_settings_errors']);
    }

    public function test_first_save_with_empty_submission_returns_empty(): void {
        $this->assertSame('', $this->sanitize(''));
    }

    public function test_parking_option_is_registered_in_its_own_group(): void {
        $this->assertSame('masjidfeed_parking_group', Masjid_Feed_Settings::PARKING_OPTION_GROUP);
        $this->assertSame('masjidfeed_parking_config', Masjid_Feed_Parking_Config::OPTION_KEY);
    }
}