<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class FirebaseClientTestCase extends TestCase {

    protected array $requests = [];
    protected array $cache_ttl = [];

    protected function service_account(): array {
        return [
            'project_id' => 'masjid-test',
            'client_email' => 'firebase-adminsdk@masjid-test.iam.gserviceaccount.com',
            'private_key' => $this->private_key_pem(),
        ];
    }

    protected function transport(int $status = 200, array $headers = [], string $body = ''): callable {
        $this->requests = [];
        return function (string $method, string $url, array $options) use ($status, $headers, $body) {
            $this->requests[] = compact('method', 'url', 'options');
            return ['status' => $status, 'headers' => $headers, 'body' => $body];
        };
    }

    protected function new_client(?callable $transport = null, array $preloaded_cache = [], array $service_account = []): Masjid_App_Firebase_Client {
        $cache = $preloaded_cache;
        return new Masjid_App_Firebase_Client(
            $service_account ?: $this->service_account(),
            $transport ?? $this->transport(),
            function (string $key) use (&$cache) {
                return $cache[$key] ?? false;
            },
            function (string $key, $value, int $ttl = 0) use (&$cache) {
                $cache[$key] = $value;
                $this->cache_ttl[$key] = $ttl;
                return true;
            }
        );
    }

    protected function token_cached_client(callable $transport): Masjid_App_Firebase_Client {
        return $this->new_client(
            $transport,
            [Masjid_App_Firebase_Client::TOKEN_CACHE_KEY => 'tok-123']
        );
    }

    protected function request_body(array $request): array {
        return json_decode($request['options']['body'], true);
    }

    private static ?string $cached_private_key = null;

    protected function private_key_pem(): string {
        if (null === self::$cached_private_key) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $pem);
            self::$cached_private_key = $pem;
        }
        return self::$cached_private_key;
    }

    protected function public_key_from_pem(string $pem): string {
        return openssl_pkey_get_details(openssl_pkey_get_private($pem))['key'];
    }
}
