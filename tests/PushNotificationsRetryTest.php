<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

class PushNotificationsRetryTest extends FirebaseClientTestCase {

    private Masjid_Feed_Push_Notifications $notifications;

    protected function setUp(): void {
        parent::setUp();
        $this->notifications = new Masjid_Feed_Push_Notifications();
    }

    private function invoke(string $method, ...$args) {
        $reflection = new ReflectionMethod(Masjid_Feed_Push_Notifications::class, $method);
        return $reflection->invoke($this->notifications, ...$args);
    }

    public function test_retryable_firebase_exceptions_are_recognized(): void {
        $this->assertTrue($this->invoke('is_retryable', new Masjid_Feed_Firebase_Api_Connection_Failed('down')));
        $this->assertTrue($this->invoke('is_retryable', new Masjid_Feed_Firebase_Quota_Exceeded('quota')));
        $this->assertTrue($this->invoke('is_retryable', new Masjid_Feed_Firebase_Server_Error('boom')));
        $this->assertTrue($this->invoke('is_retryable', new Masjid_Feed_Firebase_Server_Unavailable('unavailable')));
    }

    public function test_non_retryable_exceptions_are_recognized(): void {
        $this->assertFalse($this->invoke('is_retryable', new Masjid_Feed_Firebase_Invalid_Message('bad message')));
        $this->assertFalse($this->invoke('is_retryable', new Masjid_Feed_Firebase_Authentication_Error('bad credentials')));
        $this->assertFalse($this->invoke('is_retryable', new Masjid_Feed_Firebase_Not_Found('missing')));
        $this->assertFalse($this->invoke('is_retryable', new RuntimeException('unrelated')));
    }

    public function test_retry_delay_uses_retry_after_when_present(): void {
        $this->assertSame(
            300,
            $this->invoke('get_retry_delay', new Masjid_Feed_Firebase_Quota_Exceeded('quota', 0, null, 300), 1)
        );
    }

    public function test_retry_delay_enforces_minimum(): void {
        $this->assertSame(
            MINUTE_IN_SECONDS,
            $this->invoke('get_retry_delay', new Masjid_Feed_Firebase_Server_Unavailable('down', 0, null, 5), 1)
        );
    }

    public function test_retry_delay_falls_back_to_exponential_backoff(): void {
        $this->assertSame(
            2 * MINUTE_IN_SECONDS,
            $this->invoke('get_retry_delay', new Masjid_Feed_Firebase_Server_Error('boom'), 1)
        );
        $this->assertSame(
            HOUR_IN_SECONDS,
            $this->invoke('get_retry_delay', new Masjid_Feed_Firebase_Api_Connection_Failed('down'), 10)
        );
    }
}
