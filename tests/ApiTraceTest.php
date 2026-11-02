<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class ApiTraceTest extends TestCase {

    protected function setUp(): void {
        $GLOBALS['__test_options'] = [];
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    private function trace(): Masjid_Feed_API_Trace {
        $GLOBALS['__test_options'][Masjid_Feed_API_Trace::ENABLED_OPTION] = '1';
        return new Masjid_Feed_API_Trace();
    }

    private function registration_request(): WP_REST_Request {
        $request = new WP_REST_Request('POST', '/masjid/v1/push/registrations');
        $request->set_param('masjidId', 'masjid-x');
        $request->set_param('platform', 'ios');
        $request->set_param('token', 'current-fcm-token-1234567890');
        $request->set_header('user-agent', 'ICOB-iOS/1.0.1');
        return $request;
    }

    public function test_finish_trace_stores_the_full_response_payload(): void {
        $trace = $this->trace();
        $request = $this->registration_request();
        $trace->start_trace(null, null, $request);
        $response = new WP_REST_Response([
            'registered' => true,
            'topicKey' => [
                'site_id' => 'ms_1a2b3c4d',
                'topic' => 'tenant_ms_1a2b3c4d_v1_8f4e2c',
                'version' => 1,
                'expires_at' => '2026-11-01T12:00:00Z',
            ],
        ], 200);

        $trace->finish_trace($response, null, $request);

        $entries = Masjid_Feed_API_Trace::get_entries();
        $this->assertCount(1, $entries);
        $this->assertSame(200, $entries[0]['status']);
        $this->assertSame('1', $entries[0]['payload']['registered']);
        $this->assertSame('tenant_ms_1a2b3c4d_v1_8f4e2c', $entries[0]['payload']['topicKey']['topic']);
        $this->assertSame('ms_1a2b3c4d', $entries[0]['payload']['topicKey']['site_id']);
        $this->assertSame('1', $entries[0]['payload']['topicKey']['version']);
        $this->assertSame('[redacted]', $entries[0]['parameters']['token']);
    }

    public function test_get_requests_do_not_store_the_response_payload(): void {
        $trace = $this->trace();
        $request = new WP_REST_Request('GET', '/masjid/v1/events');
        $request->set_header('user-agent', 'ICOB-iOS/1.0.1');
        $trace->start_trace(null, null, $request);
        $response = new WP_REST_Response(['events' => array_fill(0, 20, ['title' => str_repeat('x', 500)])], 200);

        $trace->finish_trace($response, null, $request);

        $entries = Masjid_Feed_API_Trace::get_entries();
        $this->assertSame([], $entries[0]['payload']);
    }

    public function test_error_traces_keep_the_message_and_firebase_diagnostics(): void {
        $trace = $this->trace();
        $request = $this->registration_request();
        $trace->start_trace(null, null, $request);
        $error = new WP_Error('masjidfeed_device_registration_failed', 'The push service did not register the device.');
        $error->add_data([
            'status' => 502,
            'firebase' => [
                'exception' => 'Masjid_Feed_Gateway_Exception',
                'message' => 'invalid app check token',
                'httpStatus' => 401,
            ],
        ]);

        $trace->finish_trace($error, null, $request);

        $entries = Masjid_Feed_API_Trace::get_entries();
        $this->assertSame(502, $entries[0]['status']);
        $this->assertSame('masjidfeed_device_registration_failed', $entries[0]['errorCode']);
        $this->assertSame('The push service did not register the device.', $entries[0]['payload']['message']);
        $this->assertSame('Masjid_Feed_Gateway_Exception', $entries[0]['payload']['firebase']['exception']);
        $this->assertSame('401', $entries[0]['payload']['firebase']['httpStatus']);
    }

    public function test_disabled_tracing_records_nothing(): void {
        $trace = new Masjid_Feed_API_Trace();
        $request = $this->registration_request();

        $trace->start_trace(null, null, $request);
        $trace->finish_trace(new WP_REST_Response(['registered' => true], 200), null, $request);

        $this->assertSame([], Masjid_Feed_API_Trace::get_entries());
    }
}
