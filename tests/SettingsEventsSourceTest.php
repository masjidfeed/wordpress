<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SettingsEventsSourceTest extends TestCase {

    private array $previous_post;

    protected function setUp(): void {
        parent::setUp();
        $this->previous_post = $_POST;
        $_POST['_wpnonce'] = 'test-nonce';
        $GLOBALS['__test_settings_errors'] = [];
    }

    protected function tearDown(): void {
        $_POST = $this->previous_post;
        parent::tearDown();
    }

    private function sanitize(array $input): array {
        $settings = new Masjid_Feed_Settings();
        return $settings->sanitize_settings($input);
    }

    private function stored_options(array $overrides = []): array {
        return array_merge([
            'events_source' => '',
            'feature_events' => 1,
            'feature_announcements' => 1,
            'feature_donations' => 0,
            'feature_qibla' => 1,
            'feature_prayer_reminders' => 1,
            'events_categories' => [],
            'events_tags' => [],
        ], $overrides);
    }

    public function test_stored_source_falls_back_to_none_when_plugin_is_unavailable(): void {
        // The Awesome Calendar Events plugin is not active in the test environment.
        $this->assertSame(
            '',
            Masjid_Feed_Settings::resolve_events_source(['events_source' => 'awesome_calendar_events'])
        );
        $this->assertSame(
            '',
            Masjid_Feed_Settings::resolve_events_source(['events_source' => ''])
        );
    }

    public function test_stored_source_is_kept_while_the_plugin_is_available(): void {
        $this->assertSame(
            'the_events_calendar',
            Masjid_Feed_Settings::resolve_events_source(['events_source' => 'the_events_calendar'])
        );
    }

    public function test_sanitize_selecting_none_deselects_events_feature_flag(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = $this->stored_options([
            'events_source' => 'the_events_calendar',
            'feature_events' => 1,
        ]);

        $out = $this->sanitize([
            '_tab' => 'general',
            'events_source' => '',
            'feature_events' => '1',
            'feature_announcements' => '1',
        ]);

        $this->assertSame('', $out['events_source']);
        $this->assertSame(0, $out['feature_events']);
        $this->assertSame(1, $out['feature_announcements']);
    }

    public function test_sanitize_keeps_events_feature_flag_with_selected_plugin(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = $this->stored_options();

        $out = $this->sanitize([
            '_tab' => 'general',
            'events_source' => 'the_events_calendar',
            'feature_events' => '1',
        ]);

        $this->assertSame('the_events_calendar', $out['events_source']);
        $this->assertSame(1, $out['feature_events']);
    }

    public function test_sanitize_rejects_unavailable_plugin_and_disables_events(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = $this->stored_options([
            'events_source' => 'the_events_calendar',
        ]);

        $out = $this->sanitize([
            '_tab' => 'general',
            'events_source' => 'awesome_calendar_events',
            'feature_events' => '1',
        ]);

        $this->assertSame('', $out['events_source']);
        $this->assertSame(0, $out['feature_events']);
        $this->assertNotEmpty($GLOBALS['__test_settings_errors']);
    }
}
