<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class LegacySettingsMigrationTest extends TestCase {

    private array $environment = [];

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__test_options'] = [];
        foreach (['MASJIDAPP_CREDENTIAL_KEY', 'MASJIDFEED_CREDENTIAL_KEY'] as $name) {
            $this->environment[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void {
        foreach ($this->environment as $name => $value) {
            false === $value ? putenv($name) : putenv($name . '=' . $value);
        }
        parent::tearDown();
    }

    public function test_migrates_all_renamed_options(): void {
        $legacy_settings = [
            'masjid_name' => 'Legacy Masjid',
            'feature_events' => 0,
            'events_categories' => [12, 34],
            'firebase_ios_config' => ['appId' => 'ios-app'],
        ];
        $trace_entries = [['id' => 'legacy-trace']];
        $GLOBALS['__test_options'] = [
            'masjidapp_settings' => $legacy_settings,
            'masjidapp_api_trace_enabled' => '1',
            'masjidapp_api_trace_entries' => $trace_entries,
        ];

        $this->assertTrue(Masjid_Feed_Legacy_Settings_Migrator::migrate());

        $this->assertSame($legacy_settings, get_option('masjidfeed_settings'));
        $this->assertSame('1', get_option('masjidfeed_api_trace_enabled'));
        $this->assertSame($trace_entries, get_option('masjidfeed_api_trace_entries'));
    }

    public function test_existing_new_settings_win_while_missing_values_are_migrated(): void {
        $GLOBALS['__test_options'] = [
            'masjidapp_settings' => [
                'masjid_name' => 'Legacy Masjid',
                'phone' => '425-555-0100',
                'feature_events' => 0,
            ],
            'masjidfeed_settings' => [
                'masjid_name' => 'MasjidFeed Name',
                'feature_events' => 1,
            ],
            'masjidapp_api_trace_enabled' => '1',
            'masjidfeed_api_trace_enabled' => '0',
        ];

        Masjid_Feed_Legacy_Settings_Migrator::migrate();

        $this->assertSame([
            'masjid_name' => 'MasjidFeed Name',
            'phone' => '425-555-0100',
            'feature_events' => 1,
        ], get_option('masjidfeed_settings'));
        $this->assertSame('0', get_option('masjidfeed_api_trace_enabled'));
    }

    public function test_repeated_migration_does_not_replace_migrated_values(): void {
        $GLOBALS['__test_options']['masjidapp_settings'] = ['masjid_name' => 'Original'];

        Masjid_Feed_Legacy_Settings_Migrator::migrate();
        $GLOBALS['__test_options']['masjidapp_settings'] = ['masjid_name' => 'Changed legacy value'];
        Masjid_Feed_Legacy_Settings_Migrator::migrate();

        $this->assertSame(
            ['masjid_name' => 'Original'],
            get_option('masjidfeed_settings')
        );
    }

    public function test_reencrypts_legacy_credentials_with_the_new_key_and_context(): void {
        $legacy_key = str_repeat('l', 48);
        $new_key = str_repeat('n', 48);
        putenv('MASJIDAPP_CREDENTIAL_KEY=' . $legacy_key);
        putenv('MASJIDFEED_CREDENTIAL_KEY=' . $new_key);
        $credentials = [
            'project_id' => 'legacy-project',
            'client_email' => 'firebase@example.test',
            'private_key' => 'secret',
        ];
        $legacy_stored = $this->encrypt_legacy_credentials($credentials, $legacy_key);
        $GLOBALS['__test_options']['masjidapp_firebase_credentials'] = $legacy_stored;

        $this->assertTrue(Masjid_Feed_Legacy_Settings_Migrator::migrate());

        $this->assertNotSame($legacy_stored, get_option('masjidfeed_firebase_credentials'));
        $this->assertSame($credentials, Masjid_Feed_Credential_Store::get());
    }

    public function test_legacy_credential_key_remains_supported_after_migration(): void {
        $legacy_key = str_repeat('k', 48);
        putenv('MASJIDAPP_CREDENTIAL_KEY=' . $legacy_key);
        $credentials = ['project_id' => 'legacy-project'];
        $GLOBALS['__test_options']['masjidapp_firebase_credentials'] =
            $this->encrypt_legacy_credentials($credentials, $legacy_key);

        $this->assertTrue(Masjid_Feed_Legacy_Settings_Migrator::migrate());
        $this->assertSame($credentials, Masjid_Feed_Credential_Store::get());
    }

    private function encrypt_legacy_credentials(array $credentials, string $key): string {
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            (string) json_encode($credentials, JSON_UNESCAPED_SLASHES),
            Masjid_Feed_Legacy_Settings_Migrator::LEGACY_CREDENTIAL_AAD,
            $nonce,
            hash('sha256', $key, true)
        );
        return base64_encode($nonce . $ciphertext);
    }
}
