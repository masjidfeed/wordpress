<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class MasjidIdTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        unset($GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]);
    }

    protected function tearDown(): void {
        unset($GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]);
        parent::tearDown();
    }

    public function test_generate_masjid_id_has_expected_format(): void {
        $this->assertMatchesRegularExpression('/^masjid-[0-9a-f]{8}$/', Masjid_Feed_Settings::generate_masjid_id());
    }

    public function test_generate_masjid_id_is_deterministic_from_site_url(): void {
        $expected = 'masjid-' . substr(hash('sha256', 'https://example.test'), 0, 8);
        $this->assertSame($expected, Masjid_Feed_Settings::generate_masjid_id());
    }

    public function test_sync_replaces_stored_legacy_value(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_id' => 'masjidapp-test',
            'masjid_name' => 'Test Masjid',
        ];

        (new Masjid_Feed_Settings())->sync_masjid_id();

        $stored = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];
        $this->assertSame(Masjid_Feed_Settings::generate_masjid_id(), $stored['masjid_id']);
        $this->assertSame('Test Masjid', $stored['masjid_name']);
    }

    public function test_sync_keeps_other_settings_and_is_idempotent(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_id' => 'old-value',
            'masjid_name' => 'Test Masjid',
        ];
        $settings = new Masjid_Feed_Settings();
        $settings->sync_masjid_id();
        $after_first = $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY];

        $settings->sync_masjid_id();

        $this->assertSame($after_first, $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]);
    }

    public function test_sync_leaves_valid_pattern_values_untouched(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_id' => 'masjid-aaaaaaaa',
        ];

        (new Masjid_Feed_Settings())->sync_masjid_id();

        $this->assertSame('masjid-aaaaaaaa', $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]['masjid_id']);
    }

    public function test_sync_does_not_write_when_option_is_missing(): void {
        (new Masjid_Feed_Settings())->sync_masjid_id();
        $this->assertArrayNotHasKey(MASJIDFEED_OPTION_KEY, $GLOBALS['__test_options']);
    }

    public function test_sanitize_ignores_submitted_masjid_id(): void {
        $_POST['_wpnonce'] = 'test-nonce';
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_id' => 'masjidapp-test',
        ];

        $out = (new Masjid_Feed_Settings())->sanitize_settings([
            '_tab' => 'general',
            'masjid_id' => 'attacker-chosen',
            'masjid_name' => 'Test Masjid',
        ]);

        $this->assertSame(Masjid_Feed_Settings::generate_masjid_id(), $out['masjid_id']);
    }
}
