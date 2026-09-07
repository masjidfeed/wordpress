<?php
/**
 * Migrates settings from the legacy Masjid App plugin.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Legacy_Settings_Migrator {

    const LEGACY_SETTINGS_OPTION = 'masjidapp_settings';
    const LEGACY_CREDENTIAL_OPTION = 'masjidapp_firebase_credentials';
    const LEGACY_TRACE_ENABLED_OPTION = 'masjidapp_api_trace_enabled';
    const LEGACY_TRACE_ENTRIES_OPTION = 'masjidapp_api_trace_entries';
    const MIGRATION_ERROR_OPTION = 'masjidfeed_legacy_migration_error';
    const LEGACY_CREDENTIAL_AAD = 'masjid-app-firebase-v1';

    public function __construct() {
        add_action('admin_notices', array($this, 'render_migration_notice'));
    }

    /**
     * Copy legacy options without replacing values already configured in MasjidFeed.
     */
    public static function migrate() {
        self::migrate_settings();
        self::migrate_option(self::LEGACY_TRACE_ENABLED_OPTION, Masjid_Feed_API_Trace::ENABLED_OPTION);
        self::migrate_option(self::LEGACY_TRACE_ENTRIES_OPTION, Masjid_Feed_API_Trace::ENTRIES_OPTION);

        $credential_result = self::migrate_credentials();
        if (is_wp_error($credential_result)) {
            update_option(self::MIGRATION_ERROR_OPTION, $credential_result->get_error_message(), false);
            return $credential_result;
        }

        delete_option(self::MIGRATION_ERROR_OPTION);
        return true;
    }

    public function render_migration_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $error = get_option(self::MIGRATION_ERROR_OPTION, '');
        if (!is_string($error) || '' === $error) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html(sprintf(
                /* translators: %s: Firebase credential migration error */
                __('MasjidFeed migrated the legacy plugin settings, but could not migrate its Firebase credentials: %s', 'masjidfeed-app'),
                $error
            ))
        );
    }

    private static function migrate_settings() {
        $missing = new stdClass();
        $legacy = get_option(self::LEGACY_SETTINGS_OPTION, $missing);
        if (!is_array($legacy)) {
            return;
        }

        $current = get_option(MASJIDFEED_OPTION_KEY, $missing);
        if ($missing === $current) {
            add_option(MASJIDFEED_OPTION_KEY, $legacy, '', false);
            return;
        }
        if (!is_array($current)) {
            return;
        }

        $migrated = array_merge($legacy, $current);
        if ($migrated !== $current) {
            update_option(MASJIDFEED_OPTION_KEY, $migrated, false);
        }
    }

    private static function migrate_option($legacy_key, $new_key) {
        $missing = new stdClass();
        $legacy = get_option($legacy_key, $missing);
        if ($missing === $legacy || $missing !== get_option($new_key, $missing)) {
            return;
        }

        add_option($new_key, $legacy, '', false);
    }

    private static function migrate_credentials() {
        $missing = new stdClass();
        if ($missing !== get_option(MASJIDFEED_CREDENTIAL_OPTION_KEY, $missing)) {
            return true;
        }

        $legacy = get_option(self::LEGACY_CREDENTIAL_OPTION, $missing);
        if ($missing === $legacy) {
            return true;
        }

        $credentials = self::decrypt_legacy_credentials($legacy);
        if (is_wp_error($credentials)) {
            return $credentials;
        }

        return Masjid_Feed_Credential_Store::save($credentials);
    }

    private static function decrypt_legacy_credentials($stored) {
        if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
            return new WP_Error(
                'masjidfeed_legacy_encryption_unavailable',
                __('enable Sodium, then reactivate the plugin', 'masjidfeed-app')
            );
        }

        $decoded = is_string($stored) ? base64_decode($stored, true) : false;
        $nonce_size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (!is_string($decoded) || strlen($decoded) <= $nonce_size) {
            return new WP_Error(
                'masjidfeed_legacy_credentials_invalid',
                __('the stored credentials are invalid; upload the service-account JSON again', 'masjidfeed-app')
            );
        }

        foreach (self::credential_key_materials() as $key_material) {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($decoded, $nonce_size),
                self::LEGACY_CREDENTIAL_AAD,
                substr($decoded, 0, $nonce_size),
                hash('sha256', $key_material, true)
            );
            if (!is_string($plaintext)) {
                continue;
            }

            $credentials = json_decode($plaintext, true);
            if (is_array($credentials)) {
                return $credentials;
            }
        }

        return new WP_Error(
            'masjidfeed_legacy_credentials_invalid',
            __('define the previous MASJIDAPP_CREDENTIAL_KEY or upload the service-account JSON again', 'masjidfeed-app')
        );
    }

    private static function credential_key_materials() {
        $keys = array();
        foreach (array('MASJIDAPP_CREDENTIAL_KEY', 'MASJIDFEED_CREDENTIAL_KEY') as $constant) {
            if (defined($constant) && is_string(constant($constant))) {
                $keys[] = constant($constant);
            }
            $environment_key = getenv($constant);
            if (is_string($environment_key)) {
                $keys[] = $environment_key;
            }
        }

        return array_values(array_unique(array_filter($keys, static function ($key) {
            return strlen($key) >= 32;
        })));
    }
}
