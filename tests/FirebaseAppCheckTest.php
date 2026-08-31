<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

use Masjid_Feed\Dependencies\Firebase\JWT\JWT;

class FirebaseAppCheckTest extends FirebaseClientTestCase {

    private const KID = 'test-key-id';
    private ?string $signing_key_pem = null;
    private ?string $alt_key_pem = null;
    private int $jwks_fetch_count = 0;
    private string $jwks_kid = self::KID;

    protected function new_app_check_client(): Masjid_Feed_Firebase_Client {
        $cache = [];
        $client = new Masjid_Feed_Firebase_Client(
            $this->service_account(),
            function (string $method, string $url, array $options) {
                $this->requests[] = compact('method', 'url', 'options');
                if ('GET' === $method && Masjid_Feed_Firebase_Client::APP_CHECK_JWKS_URL === $url) {
                    $this->jwks_fetch_count++;
                    return ['status' => 200, 'headers' => [], 'body' => json_encode($this->jwks())];
                }
                $this->fail('Unexpected transport call: ' . $method . ' ' . $url);
            },
            function (string $key) use (&$cache) {
                return $cache[$key] ?? false;
            },
            function (string $key, $value, int $ttl = 0) use (&$cache) {
                $cache[$key] = $value;
                return true;
            }
        );
        return $client;
    }

    protected function setUp(): void {
        parent::setUp();
        $this->jwks_fetch_count = 0;
        $this->jwks_kid = self::KID;
        $this->signing_key_pem = $this->generate_key();
        $this->alt_key_pem = $this->generate_key();
    }

    private function generate_key(): string {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        return $pem;
    }

    private function jwks_for(string $pem, string $kid): array {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($pem))['rsa'];
        return [
            'keys' => [[
                'kty' => 'RSA',
                'kid' => $kid,
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => $this->base64url_encode($details['n']),
                'e' => $this->base64url_encode($details['e']),
            ]],
        ];
    }

    private function jwks(): array {
        $pem = self::KID === $this->jwks_kid ? $this->signing_key_pem : $this->alt_key_pem;
        return $this->jwks_for($pem, $this->jwks_kid);
    }

    private function base64url_encode(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function make_token(?string $kid = self::KID, ?array $overrides = null, ?string $pem = null): string {
        $claims = array_merge([
            'aud' => ['projects/masjid-test'],
            'exp' => time() + 300,
            'iat' => time() - 10,
            'iss' => 'https://firebaseappcheck.googleapis.com/12345678',
            'sub' => '1:234567890:android:abcdef',
        ], $overrides ?? []);
        $key_id = $kid;
        $key_pem = $pem ?? ($kid === self::KID ? $this->signing_key_pem : $this->alt_key_pem);
        return JWT::encode($claims, $key_pem, 'RS256', $key_id);
    }

    public function test_valid_token_returns_app_id(): void {
        $client = $this->new_app_check_client();
        $payload = $client->verify_app_check_token($this->make_token());
        $this->assertSame('1:234567890:android:abcdef', $payload['app_id']);
        $this->assertSame($payload['sub'], $payload['app_id']);
        $this->assertSame(['projects/masjid-test'], $payload['aud']);
        $this->assertSame(1, $this->jwks_fetch_count);
    }

    public function test_jwks_are_cached_between_verifications(): void {
        $client = $this->new_app_check_client();
        $client->verify_app_check_token($this->make_token());
        $client->verify_app_check_token($this->make_token());
        $this->assertSame(1, $this->jwks_fetch_count);
    }

    public function test_unknown_kid_triggers_jwks_refresh(): void {
        $client = $this->new_app_check_client();
        $client->verify_app_check_token($this->make_token());

        $this->jwks_kid = 'rotated-key-id';
        $payload = $client->verify_app_check_token($this->make_token('rotated-key-id'));
        $this->assertSame('1:234567890:android:abcdef', $payload['app_id']);
        $this->assertSame(2, $this->jwks_fetch_count);
    }

    public function test_wrong_audience_is_rejected(): void {
        $client = $this->new_app_check_client();
        $this->expectException(Masjid_Feed_Firebase_Failed_Verify_App_Check_Token::class);
        $this->expectExceptionMessage('aud');
        $client->verify_app_check_token($this->make_token(overrides: ['aud' => ['projects/other-project']]));
    }

    public function test_wrong_issuer_is_rejected(): void {
        $client = $this->new_app_check_client();
        $this->expectException(Masjid_Feed_Firebase_Failed_Verify_App_Check_Token::class);
        $this->expectExceptionMessage('iss');
        $client->verify_app_check_token($this->make_token(overrides: ['iss' => 'https://evil.example.com/']));
    }

    public function test_expired_token_is_rejected(): void {
        $client = $this->new_app_check_client();
        $this->expectException(Masjid_Feed_Firebase_Invalid_App_Check_Token::class);
        $client->verify_app_check_token($this->make_token(overrides: ['exp' => time() - 600, 'iat' => time() - 1200]));
    }

    public function test_wrong_signing_key_is_rejected(): void {
        $client = $this->new_app_check_client();
        $this->expectException(Masjid_Feed_Firebase_Failed_Verify_App_Check_Token::class);
        $client->verify_app_check_token($this->make_token(kid: 'other-kid', pem: $this->alt_key_pem));
    }

    public function test_malformed_token_is_rejected(): void {
        $client = $this->new_app_check_client();
        $this->expectException(Masjid_Feed_Firebase_Failed_Verify_App_Check_Token::class);
        $client->verify_app_check_token('not-a-jwt');
    }

    public function test_jwks_http_error_is_reported(): void {
        $this->jwks_kid = 'unfetched';
        $client = $this->new_app_check_client();
        // Bypass the JWKS endpoint by pointing the transport at a failing response.
        $client = new Masjid_Feed_Firebase_Client(
            $this->service_account(),
            fn () => ['status' => 500, 'headers' => [], 'body' => '{}'],
            fn () => false,
            fn () => true
        );
        $this->expectException(Masjid_Feed_Firebase_Failed_Verify_App_Check_Token::class);
        $client->verify_app_check_token($this->make_token('unfetched-kid'));
    }
}
