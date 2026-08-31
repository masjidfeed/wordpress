<?php
/**
 * Encrypted Firebase service-account storage.
 */

if (!defined('ABSPATH')) { exit; }

class Masjid_Feed_Credential_Store {

    const AAD = 'masjidfeed-app-firebase-v1';

    public static function is_available() {
        return strlen(self::key_material()) >= 32
            && function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt');
    }

    public static function save(array $credentials) {
        if (!self::is_available()) {
            return new WP_Error('masjidfeed_encryption_unavailable', __('Define MASJIDFEED_CREDENTIAL_KEY with at least 32 characters and enable Sodium before saving Firebase credentials.', 'masjidfeed-app'));
        }

        $json = wp_json_encode($credentials, JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return new WP_Error('masjidfeed_invalid_credentials', __('Firebase credentials could not be encoded.', 'masjidfeed-app'));
        }

        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($json, self::AAD, $nonce, self::key());
        update_option(MASJIDFEED_CREDENTIAL_OPTION_KEY, base64_encode($nonce . $ciphertext), false);
        return true;
    }

    public static function get() {
        if (!self::is_available()) {
            return new WP_Error('masjidfeed_encryption_unavailable', __('Firebase credential encryption is unavailable.', 'masjidfeed-app'));
        }

        $stored = get_option(MASJIDFEED_CREDENTIAL_OPTION_KEY, '');
        $decoded = is_string($stored) ? base64_decode($stored, true) : false;
        $nonce_size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (!is_string($decoded) || strlen($decoded) <= $nonce_size) {
            return new WP_Error('masjidfeed_credentials_missing', __('Firebase service-account credentials have not been configured.', 'masjidfeed-app'));
        }

        $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
            substr($decoded, $nonce_size),
            self::AAD,
            substr($decoded, 0, $nonce_size),
            self::key()
        );
        if (!is_string($plaintext)) {
            return new WP_Error('masjidfeed_credentials_invalid', __('Firebase credentials could not be decrypted. Re-upload them after checking the credential key.', 'masjidfeed-app'));
        }

        $credentials = json_decode($plaintext, true);
        return is_array($credentials)
            ? $credentials
            : new WP_Error('masjidfeed_credentials_invalid', __('Stored Firebase credentials are invalid.', 'masjidfeed-app'));
    }

    public static function has_credentials() {
        return is_string(get_option(MASJIDFEED_CREDENTIAL_OPTION_KEY, ''))
            && '' !== get_option(MASJIDFEED_CREDENTIAL_OPTION_KEY, '');
    }

    public static function delete() {
        delete_option(MASJIDFEED_CREDENTIAL_OPTION_KEY);
    }

    private static function key() {
        return hash('sha256', self::key_material(), true);
    }

    private static function key_material() {
        if (defined('MASJIDFEED_CREDENTIAL_KEY') && is_string(MASJIDFEED_CREDENTIAL_KEY)) {
            return MASJIDFEED_CREDENTIAL_KEY;
        }
        $environment_key = getenv('MASJIDFEED_CREDENTIAL_KEY');
        return is_string($environment_key) ? $environment_key : '';
    }
}