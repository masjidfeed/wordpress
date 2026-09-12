<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

use Masjid_Feed\Dependencies\Firebase\JWT\JWT;

class FirebaseClientHttpTest extends FirebaseClientTestCase {

    public function test_missing_service_account_fields_throw(): void {
        $this->expectException(Masjid_Feed_Firebase_Exception::class);
        new Masjid_Feed_Firebase_Client([], fn() => []);
    }

    public function test_access_token_signs_service_account_jwt_and_caches(): void {
        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured) {
            $captured[] = compact('method', 'url', 'options');
            return ['status' => 200, 'headers' => [], 'body' => json_encode(['access_token' => 'tok-123', 'expires_in' => 3600])];
        };
        $client = $this->new_client($transport);

        $this->assertSame('tok-123', $client->access_token());
        $this->assertSame('tok-123', $client->access_token());
        $this->assertCount(1, $captured);

        $request = $captured[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame(Masjid_Feed_Firebase_Client::OAUTH_TOKEN_URL, $request['url']);
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['options']['body']['grant_type']);
        $this->assertArrayHasKey('assertion', $request['options']['body']);

        $assertion = $request['options']['body']['assertion'];
        $key = new Masjid_Feed\Dependencies\Firebase\JWT\Key($this->public_key_from_pem($this->private_key_pem()), 'RS256');
        $claims = JWT::decode($assertion, $key);
        $this->assertSame('firebase-adminsdk@masjid-test.iam.gserviceaccount.com', $claims->iss);
        $this->assertSame($claims->iss, $claims->sub);
        $this->assertSame(Masjid_Feed_Firebase_Client::OAUTH_TOKEN_URL, $claims->aud);
        $this->assertStringContainsString('firebase.messaging', $claims->scope);
        $this->assertSame(3540, $this->cache_ttl[Masjid_Feed_Firebase_Client::TOKEN_CACHE_KEY]);
    }

    public function test_access_token_http_error_throws_authentication_error(): void {
        $client = $this->new_client($this->transport(401, [], json_encode(['error' => 'invalid_grant'])));
        $this->expectException(Masjid_Feed_Firebase_Authentication_Error::class);
        $client->access_token();
    }

    public function test_access_token_missing_token_in_response_throws(): void {
        $client = $this->new_client($this->transport(200, [], json_encode(['expires_in' => 3600])));
        $this->expectException(Masjid_Feed_Firebase_Authentication_Error::class);
        $client->access_token();
    }

    public function test_wordpress_transport_passes_http_method_to_wp_remote_request(): void {
        $GLOBALS['__test_wp_remote_request_response'] = [
            'response' => ['code' => 200],
            'headers' => ['Content-Type' => 'application/json'],
            'body' => '{}',
        ];
        try {
            $client = new Masjid_Feed_Firebase_Client($this->service_account());
            $response = $client->wordpress_transport('POST', 'https://example.test/endpoint', ['body' => 'payload']);

            $this->assertSame(200, $response['status']);
            $sent = end($GLOBALS['__test_wp_remote_requests']);
            $this->assertSame('POST', $sent['args']['method']);
            $this->assertSame('https://example.test/endpoint', $sent['url']);
            $this->assertSame('application/json', $response['headers']['content-type']);
        } finally {
            unset($GLOBALS['__test_wp_remote_request_response']);
            $GLOBALS['__test_wp_remote_requests'] = [];
        }
    }

    public function test_wordpress_transport_converts_wp_error_to_connection_failed(): void {
        $client = new Masjid_Feed_Firebase_Client($this->service_account());
        $this->expectException(Masjid_Feed_Firebase_Api_Connection_Failed::class);
        $client->wordpress_transport('GET', 'https://example.test/endpoint', []);
    }

    public function test_access_token_with_fresh_flag_bypasses_cache(): void {
        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured) {
            $captured[] = compact('method', 'url');
            return ['status' => 200, 'headers' => [], 'body' => json_encode(['access_token' => 'tok-fresh', 'expires_in' => 3600])];
        };
        $client = $this->token_cached_client($transport);

        $this->assertSame('tok-fresh', $client->access_token(true));
        $this->assertCount(1, $captured);
        $this->assertSame('POST', $captured[0]['method']);
    }

    public function test_access_token_without_fresh_flag_uses_cache(): void {
        $captured = [];
        $transport = function () use (&$captured) {
            $captured[] = true;
            return ['status' => 200, 'headers' => [], 'body' => '{}'];
        };
        $client = $this->token_cached_client($transport);

        $this->assertSame('tok-123', $client->access_token());
        $this->assertCount(0, $captured);
    }

    public function test_oauth_failure_carries_raw_response_details(): void {
        $body = json_encode(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.']);
        $client = $this->new_client($this->transport(400, [], $body));
        try {
            $client->access_token();
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Authentication_Error $exception) {
            $this->assertSame(400, $exception->http_status());
            $this->assertSame($body, $exception->response_body());
            $this->assertStringContainsString('Invalid JWT Signature', $exception->getMessage());
        }
    }

    public function test_app_check_keys_can_be_fetched_and_counts_keys(): void {
        $details = openssl_pkey_get_details(openssl_pkey_get_private($this->private_key_pem()));
        $modulus = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $jwks = json_encode(['keys' => [
            ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'n' => $modulus, 'e' => 'AQAB', 'kid' => 'kid-1'],
        ]]);
        $client = $this->new_client($this->transport(200, [], $jwks));
        $keys = $client->app_check_keys(false);
        $this->assertCount(1, $keys);
    }

    public function test_send_message_uses_fcm_v1_endpoint_with_bearer_token(): void {
        $client = $this->token_cached_client(
            $this->transport(200, [], json_encode(['name' => 'projects/masjid-test/messages/abc']))
        );
        $result = $client->send_message([
            'message' => ['topic' => 'masjid_x', 'notification' => ['title' => 'T', 'body' => 'B']],
        ]);

        $this->assertCount(1, $this->requests);
        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://fcm.googleapis.com/v1/projects/masjid-test/messages:send', $request['url']);
        $this->assertSame('Bearer tok-123', $request['options']['headers']['Authorization']);
        $this->assertSame('application/json; charset=UTF-8', $request['options']['headers']['Content-Type']);
        $this->assertSame('masjid_x', $this->request_body($request)['message']['topic']);
        $this->assertArrayNotHasKey('validate_only', $this->request_body($request));
        $this->assertSame('projects/masjid-test/messages/abc', $result['name']);
    }

    public function test_send_message_validate_only(): void {
        $client = $this->token_cached_client($this->transport(200, [], '{}'));
        $client->send_message(['message' => ['topic' => 't']], true);
        $this->assertTrue($this->request_body($this->requests[0])['validate_only']);
    }

    public function test_subscribe_returns_topic_result_shape(): void {
        $client = $this->token_cached_client($this->transport(200, [], json_encode(['results' => [[]]])));
        $result = $client->subscribe_to_topic('masjid_x', 'device-token');
        $this->assertSame(['masjid_x' => ['device-token' => 'OK']], $result);

        $request = $this->requests[0];
        $this->assertSame('https://iid.googleapis.com/iid/v1:batchAdd', $request['url']);
        $this->assertSame(
            ['to' => '/topics/masjid_x', 'registration_tokens' => ['device-token']],
            $this->request_body($request)
        );
    }

    public function test_subscribe_maps_token_errors(): void {
        $client = $this->token_cached_client(
            $this->transport(200, [], json_encode(['results' => [['error' => 'INVALID_ARGUMENT']]]))
        );
        $result = $client->subscribe_to_topic('masjid_x', 'device-token');
        $this->assertSame(['masjid_x' => ['device-token' => 'INVALID_ARGUMENT']], $result);
    }

    public function test_unsubscribe_uses_batch_remove(): void {
        $client = $this->token_cached_client($this->transport(200, [], json_encode(['results' => [[]]])));
        $client->unsubscribe_from_topic('masjid_x', 'device-token');
        $this->assertSame('https://iid.googleapis.com/iid/v1:batchRemove', $this->requests[0]['url']);
    }

    public function test_malformed_json_response_throws_server_error(): void {
        $client = $this->token_cached_client($this->transport(200, [], 'not-json'));
        $this->expectException(Masjid_Feed_Firebase_Server_Error::class);
        $client->send_message(['message' => ['topic' => 't']]);
    }

    public static function status_exception_provider(): array {
        return [
            'bad request' => [400, Masjid_Feed_Firebase_Invalid_Message::class, false],
            'unauthorized' => [401, Masjid_Feed_Firebase_Authentication_Error::class, false],
            'forbidden' => [403, Masjid_Feed_Firebase_Authentication_Error::class, false],
            'not found' => [404, Masjid_Feed_Firebase_Not_Found::class, false],
            'quota' => [429, Masjid_Feed_Firebase_Quota_Exceeded::class, true],
            'server error' => [500, Masjid_Feed_Firebase_Server_Error::class, true],
            'unavailable' => [503, Masjid_Feed_Firebase_Server_Unavailable::class, true],
            'unknown' => [418, Masjid_Feed_Firebase_Exception::class, false],
        ];
    }

    #[PHPUnit\Framework\Attributes\DataProvider('status_exception_provider')]
    public function test_error_status_maps_to_exception(int $status, string $class, bool $retryable): void {
        $client = $this->token_cached_client($this->transport(
            $status,
            [],
            json_encode(['error' => ['code' => $status, 'message' => 'Boom']])
        ));
        try {
            $client->send_message(['message' => ['topic' => 't']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Exception $exception) {
            $this->assertInstanceOf($class, $exception);
            $this->assertSame($retryable, $exception->is_retryable());
            $this->assertStringContainsString('Boom', $exception->getMessage());
            $this->assertStringContainsString('(HTTP ' . $status . ')', $exception->getMessage());
            $this->assertSame($status, $exception->http_status());
            $this->assertStringContainsString('Boom', $exception->response_body());
        }
    }

    public function test_subscribe_http_failure_carries_response_details(): void {
        $body = json_encode(['error' => ['code' => 404, 'message' => 'Topic not found']]);
        $client = $this->token_cached_client($this->transport(404, [], $body));
        try {
            $client->subscribe_to_topic('masjid_x', 'device-token');
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Exception $exception) {
            $this->assertSame(404, $exception->http_status());
            $this->assertSame($body, $exception->response_body());
        }
    }

    public function test_quota_exceeded_reads_numeric_retry_after(): void {
        $client = $this->token_cached_client(
            $this->transport(429, ['Retry-After' => '120'], json_encode(['error' => ['message' => 'quota']]))
        );
        try {
            $client->send_message(['message' => ['topic' => 't']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Quota_Exceeded $exception) {
            $this->assertSame(120, $exception->retry_after());
        }
    }

    public function test_retry_after_http_date_is_converted_to_seconds(): void {
        $client = $this->token_cached_client(
            $this->transport(503, ['Retry-After' => gmdate('r', time() + 90)], json_encode(['error' => ['message' => 'down']]))
        );
        try {
            $client->send_message(['message' => ['topic' => 't']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Server_Unavailable $exception) {
            $this->assertNotNull($exception->retry_after());
            $this->assertGreaterThan(0, $exception->retry_after());
            $this->assertLessThanOrEqual(90, $exception->retry_after());
        }
    }

    public function test_case_insensitive_retry_after_header(): void {
        $client = $this->token_cached_client(
            $this->transport(429, ['RETRY-AFTER' => '45'], json_encode(['error' => ['message' => 'quota']]))
        );
        try {
            $client->send_message(['message' => ['topic' => 't']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Quota_Exceeded $exception) {
            $this->assertSame(45, $exception->retry_after());
        }
    }

    public function test_missing_retry_after_header_yields_null(): void {
        $client = $this->token_cached_client(
            $this->transport(500, [], json_encode(['error' => ['message' => 'boom']]))
        );
        try {
            $client->send_message(['message' => ['topic' => 't']]);
            $this->fail('Expected an exception.');
        } catch (Masjid_Feed_Firebase_Server_Error $exception) {
            $this->assertNull($exception->retry_after());
        }
    }
}
