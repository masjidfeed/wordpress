<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

class PushNotificationsReceiptTest extends FirebaseClientTestCase {

    public function test_gateway_receipt_wins_when_present(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([
            'gateway' => 'projects/masjidapp-bcfc7/messages/123',
            'fcm' => 'projects/masjidapp-bcfc7/messages/456',
        ]);

        $this->assertSame(Masjid_Feed_Settings::PUSH_MODE_MASJIDFEED, $receipt['channel']);
        $this->assertSame('projects/masjidapp-bcfc7/messages/123', $receipt['send_id']);
    }

    public function test_fcm_receipt_is_used_when_the_gateway_returned_none(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([
            'gateway' => '',
            'fcm' => 'projects/masjidapp-bcfc7/messages/456',
        ]);

        $this->assertSame(Masjid_Feed_Settings::PUSH_MODE_FCM, $receipt['channel']);
        $this->assertSame('projects/masjidapp-bcfc7/messages/456', $receipt['send_id']);
    }

    public function test_fcm_only_mode_returns_the_fcm_receipt(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([
            'fcm' => 'projects/masjidapp-bcfc7/messages/789',
        ]);

        $this->assertSame(Masjid_Feed_Settings::PUSH_MODE_FCM, $receipt['channel']);
        $this->assertSame('projects/masjidapp-bcfc7/messages/789', $receipt['send_id']);
    }

    public function test_first_non_empty_result_wins_regardless_of_channel_order(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([
            'fcm' => 'projects/masjidapp-bcfc7/messages/111',
            'gateway' => 'projects/masjidapp-bcfc7/messages/222',
        ]);

        $this->assertSame(Masjid_Feed_Settings::PUSH_MODE_FCM, $receipt['channel']);
        $this->assertSame('projects/masjidapp-bcfc7/messages/111', $receipt['send_id']);
    }

    public function test_no_result_yields_an_empty_receipt(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([
            'gateway' => '',
            'fcm' => '',
        ]);

        $this->assertSame('', $receipt['channel']);
        $this->assertSame('', $receipt['send_id']);
    }

    public function test_empty_results_yield_an_empty_receipt(): void {
        $receipt = Masjid_Feed_Push_Notifications::pick_receipt([]);

        $this->assertSame('', $receipt['channel']);
        $this->assertSame('', $receipt['send_id']);
    }
}
