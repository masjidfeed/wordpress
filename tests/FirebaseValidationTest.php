<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

use PHPUnit\Framework\TestCase;

class FirebaseValidationTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_transients'] = [];
        $GLOBALS['__test_wp_remote_requests'] = [];
        putenv('MASJIDFEED_CREDENTIAL_KEY=' . str_repeat('v', 48));
    }

    protected function tearDown(): void {
        putenv('MASJIDFEED_CREDENTIAL_KEY=');
        unset($GLOBALS['__test_wp_remote_request_response']);
    }

    private function store_service_account(): void {
        $credentials = [
            'project_id' => 'masjid-test',
            'client_email' => 'firebase-adminsdk@masjid-test.iam.gserviceaccount.com',
            'private_key' => $this->private_key_pem(),
        ];
        $this->assertTrue(Masjid_Feed_Credential_Store::save($credentials));
    }

    private function private_key_pem(): string {
        static $pem = null;
        if (null === $pem) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $pem);
        }
        return $pem;
    }

    private function stored_configs(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'firebase_project_id' => 'masjid-test',
            'firebase_ios_config' => [
                'appId' => '1:123:ios:abc',
                'senderId' => '123',
                'projectId' => 'masjid-test',
                'apiKey' => 'AIza-ios',
                'bundleId' => 'com.goodsoftware.masjidapp',
            ],
            'firebase_android_config' => [
                'appId' => '1:123:android:def',
                'senderId' => '123',
                'projectId' => 'masjid-test',
                'apiKey' => 'AIza-android',
                'packageName' => 'com.goodsoftware.masjidapp',
            ],
        ];
    }

    private function status_by_label(array $checks, string $label): array {
        foreach ($checks as $check) {
            if ($check['label'] === $label) {
                return $check;
            }
        }
        $this->fail('Missing check: ' . $label);
    }

    public function test_missing_credentials_fails_service_account_and_skips_network_checks(): void {
        $checks = Masjid_Feed_Firebase::validate_configuration();

        $service = $this->status_by_label($checks, 'Stored service-account JSON');
        $this->assertSame('fail', $service['status']);
        $this->assertStringContainsString('have not been configured', $service['detail']);

        $oauth = $this->status_by_label($checks, 'Firebase OAuth access token');
        $this->assertSame('skipped', $oauth['status']);

        $fcm = $this->status_by_label($checks, 'FCM validate-only send');
        $this->assertSame('skipped', $fcm['status']);
    }

    public function test_undecryptable_stored_credentials_fail_and_skip_network_checks(): void {
        $GLOBALS['__test_options'][MASJIDFEED_CREDENTIAL_OPTION_KEY] = base64_encode(random_bytes(120));

        $checks = Masjid_Feed_Firebase::validate_configuration();

        $this->assertSame('fail', $this->status_by_label($checks, 'Stored service-account JSON')['status']);
        $this->assertStringContainsString('could not be decrypted', $this->status_by_label($checks, 'Stored service-account JSON')['detail']);
        $this->assertSame('skipped', $this->status_by_label($checks, 'Firebase App Check public keys')['status']);
        $this->assertSame('skipped', $this->status_by_label($checks, 'FCM validate-only send')['status']);
    }

    public function test_config_checks_pass_and_network_checks_report_failure(): void {
        $this->store_service_account();
        $this->stored_configs();

        $checks = Masjid_Feed_Firebase::validate_configuration();

        $this->assertSame('pass', $this->status_by_label($checks, 'Stored service-account JSON')['status']);
        $this->assertStringContainsString('masjid-test', $this->status_by_label($checks, 'Stored service-account JSON')['detail']);

        $this->assertSame('pass', $this->status_by_label($checks, 'iOS GoogleService-Info.plist configuration')['status']);
        $this->assertSame('pass', $this->status_by_label($checks, 'Android google-services.json configuration')['status']);
        $this->assertSame('pass', $this->status_by_label($checks, 'Firebase project consistency')['status']);

        // No HTTP transport in tests: the OAuth fetch fails with the endpoint in the detail.
        $oauth = $this->status_by_label($checks, 'Firebase OAuth access token');
        $this->assertSame('fail', $oauth['status']);
        $this->assertStringContainsString(Masjid_Feed_Firebase_Client::OAUTH_TOKEN_URL, $oauth['detail']);

        $app_check = $this->status_by_label($checks, 'Firebase App Check public keys');
        $this->assertSame('fail', $app_check['status']);
        $this->assertStringContainsString(Masjid_Feed_Firebase_Client::APP_CHECK_JWKS_URL, $app_check['detail']);

        $this->assertSame('skipped', $this->status_by_label($checks, 'FCM validate-only send')['status']);
    }

    public function test_project_mismatch_fails_consistency_check(): void {
        $this->store_service_account();
        $this->stored_configs();
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY]['firebase_ios_config']['projectId'] = 'other-project';

        $checks = Masjid_Feed_Firebase::validate_configuration();

        $consistency = $this->status_by_label($checks, 'Firebase project consistency');
        $this->assertSame('fail', $consistency['status']);
        $this->assertStringContainsString('other-project', $consistency['detail']);
    }

    public function test_unconfigured_files_fail_their_checks(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [];

        $checks = Masjid_Feed_Firebase::validate_configuration();

        $this->assertSame('fail', $this->status_by_label($checks, 'iOS GoogleService-Info.plist configuration')['status']);
        $this->assertSame('fail', $this->status_by_label($checks, 'Android google-services.json configuration')['status']);
        $this->assertStringContainsString('appId', $this->status_by_label($checks, 'iOS GoogleService-Info.plist configuration')['detail']);
    }

    public function test_render_validation_results_outputs_table_and_response_body(): void {
        set_transient('masjidfeed_firebase_validation', [
            ['label' => 'Firebase OAuth access token', 'status' => 'fail', 'detail' => 'POST https://oauth2.googleapis.com/token → Boom (HTTP 404)', 'response' => '<html>404 body</html>'],
            ['label' => 'Stored service-account JSON', 'status' => 'pass', 'detail' => 'Project masjid-test.', 'response' => ''],
            ['label' => 'FCM validate-only send', 'status' => 'skipped', 'detail' => 'Skipped', 'response' => ''],
        ], 300);

        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_validation_results');
        $method->invoke($settings);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('Validation results', $html);
        $this->assertStringContainsString('Firebase OAuth access token', $html);
        $this->assertStringContainsString('Failed', $html);
        $this->assertStringContainsString('#d63638', $html);
        $this->assertStringContainsString('HTTP response body', $html);
        $this->assertStringContainsString('&lt;html&gt;404 body&lt;/html&gt;', $html);
        $this->assertStringContainsString('Pass', $html);
        $this->assertStringContainsString('Skipped', $html);
        $this->assertFalse(get_transient('masjidfeed_firebase_validation'), 'The transient should be cleared after rendering.');
    }

    public function test_validation_results_are_hidden_without_transient(): void {
        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_validation_results');
        $method->invoke($settings);
        $html = (string) ob_get_clean();

        $this->assertSame('', $html);
    }
}