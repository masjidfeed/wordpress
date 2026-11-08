<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class SettingsRenderTest extends TestCase {

    private function render_general_tab(array $opts, array $categories = [], array $tags = []): string {
        $GLOBALS['__test_terms_list']['category'] = $categories;
        $GLOBALS['__test_terms_list'][Masjid_Feed_Event_Source_The_Events_Calendar::EVENTS_TAXONOMY] = [
            new WP_Term(['term_id' => 11, 'taxonomy' => Masjid_Feed_Event_Source_The_Events_Calendar::EVENTS_TAXONOMY, 'slug' => 'ramadan', 'name' => 'Ramadan']),
        ];

        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_general_tab');
        $method->invoke($settings, $opts, $categories, $tags);
        return (string) ob_get_clean();
    }

    private function base_options(): array {
        return Masjid_Feed_Settings::get_all_options();
    }

    public function test_general_tab_renders_with_no_events_source(): void {
        $html = $this->render_general_tab($this->base_options());

        $this->assertStringContainsString('The Events Calendar', $html);
        $this->assertStringContainsString('Awesome Calendar Events', $html);
    }

    public function test_general_tab_renders_with_the_events_calendar_selected(): void {
        $opts = $this->base_options();
        $opts['events_source'] = 'the_events_calendar';

        $html = $this->render_general_tab($opts);

        $this->assertStringContainsString('ramadan', $html);
        $this->assertStringContainsString('data-for-tec', $html);
    }

    public function test_general_tab_renders_with_awesome_calendar_events_selected(): void {
        $opts = $this->base_options();
        $opts['events_source'] = 'awesome_calendar_events';

        $html = $this->render_general_tab($opts);

        $this->assertStringContainsString('data-for-default', $html);
    }

    public function test_general_tab_drops_events_donations_and_feature_flag_announcements(): void {
        $html = $this->render_general_tab($this->base_options());

        // The Events flag has no checkbox: it derives from the Events Plugin
        // selector. Donations derives from the donation URL. The
        // announcements flag moved under its own section.
        $this->assertStringNotContainsString('[feature_events]', $html);
        $this->assertStringNotContainsString('[feature_donations]', $html);
        $this->assertStringNotContainsString('Announcements Filter', $html);

        // Qibla and Prayer Reminders remain configurable flags.
        $this->assertStringContainsString('[feature_qibla]', $html);
        $this->assertStringContainsString('[feature_prayer_reminders]', $html);
    }

    public function test_announcements_section_holds_the_enable_flag(): void {
        $html = $this->render_general_tab($this->base_options());

        $this->assertStringContainsString('Enable announcements', $html);
        $this->assertStringContainsString('[feature_announcements]', $html);
        // The checkbox lives in the Announcements section, after the section
        // header and before the filter controls.
        $header_at = strpos($html, 'Announcements</h2>');
        $flag_at = strpos($html, '[feature_announcements]');
        $categories_at = strpos($html, 'Friday Category');
        $this->assertNotFalse($header_at);
        $this->assertNotFalse($flag_at);
        $this->assertNotFalse($categories_at);
        $this->assertLessThan($flag_at, $header_at);
        $this->assertLessThan($categories_at, $flag_at);
    }
}
