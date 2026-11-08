<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class PreviewTabTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['__test_options'] = [];
        $GLOBALS['__test_transients'] = [];
        $GLOBALS['__test_posts'] = [];
        unset($GLOBALS['__test_current_user_can']);
    }

    private function render_preview_tab(): string {
        $settings = new Masjid_Feed_Settings();
        ob_start();
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'render_preview_tab');
        $method->invoke($settings);
        return (string) ob_get_clean();
    }

    private function render_settings_page(string $tab): string {
        $previous_get = $_GET;
        $_GET = [
            'page' => Masjid_Feed_Settings::PAGE_SLUG,
            'tab' => $tab,
            '_wpnonce' => 'test-nonce',
        ];
        $settings = new Masjid_Feed_Settings();
        ob_start();
        try {
            $settings->render_settings_page();
        } finally {
            $_GET = $previous_get;
        }
        return (string) ob_get_clean();
    }

    public function test_preview_tab_renders_iphone_skeleton(): void {
        $html = $this->render_preview_tab();

        // The frame and the controls the JS drives.
        $this->assertStringContainsString('masjidfeed-preview-root', $html);
        $this->assertStringContainsString('masjidfeed-preview-logo', $html);
        $this->assertStringContainsString('masjidfeed-preview-name', $html);
        $this->assertStringContainsString('masjidfeed-preview-announcements', $html);
        $this->assertStringContainsString('masjidfeed-preview-countdown', $html);
        // The app chrome mirrors the screenshot: app tabs, the prayer
        // widget with placeholder times, and the bottom bar.
        $this->assertStringContainsString('Announcements', $html);
        $this->assertStringContainsString('Latest Announcements', $html);
        $this->assertStringContainsString('Dhuhr', $html);
        $this->assertStringContainsString('6:30', $html);
        $this->assertStringContainsString('Qibla', $html);
        // The primary color is wired as a CSS custom property.
        $this->assertStringContainsString('--masjidfeed-primary', $html);

        // The Ramadan tab sits between Announcements and Events, hidden
        // until the config confirms the feature flag is on.
        $this->assertStringContainsString('masjidfeed-preview-ramadan-tab', $html);
        $this->assertStringContainsString('hidden', $html);
        $announcements_at = strpos($html, 'Announcements');
        $ramadan_tab_at = strpos($html, 'masjidfeed-preview-ramadan-tab');
        $events_at = strpos($html, '>Events<');
        $this->assertNotFalse($announcements_at);
        $this->assertNotFalse($ramadan_tab_at);
        $this->assertNotFalse($events_at);
        $this->assertLessThan($ramadan_tab_at, $announcements_at);
        $this->assertLessThan($events_at, $ramadan_tab_at);
    }

    public function test_preview_tab_renders_the_config_server_side(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_name' => 'Islamic Center of Bothell',
            'primary_color' => '#123456',
            'ramadan_url' => 'https://example.test/ramadan/',
            'logo_id' => 0,
        ];

        $html = $this->render_preview_tab();

        $this->assertStringContainsString('Islamic Center of Bothell', $html);
        $this->assertStringContainsString('--masjidfeed-primary: #123456', $html);
        // The Ramadan tab is visible when the flag is on and the URL is set.
        $this->assertStringNotContainsString('masjidfeed-preview-ramadan-tab" hidden', $html);
    }

    public function test_preview_tab_hides_ramadan_tab_when_flag_off(): void {
        $GLOBALS['__test_options'][MASJIDFEED_OPTION_KEY] = [
            'masjid_name' => 'Islamic Center of Bothell',
            'ramadan_url' => '',
            'logo_id' => 0,
        ];

        $html = $this->render_preview_tab();

        $this->assertStringContainsString('masjidfeed-preview-ramadan-tab" hidden', $html);
    }

    public function test_preview_tab_renders_announcements_server_side(): void {
        $GLOBALS['__test_transients'][Masjid_Feed_REST_API::REGULAR_ANNOUNCEMENTS_CACHE_KEY] = [
            'data' => [
                [
                    'postId' => 5,
                    'title' => '<b>We&rsquo;re Hiring</b>',
                    'description' => '<p>Alhamdulillah,   we are growing </p>',
                    'publishedAt' => '2026-10-27T17:15:00+00:00',
                    'image' => 'https://example.test/a.jpg',
                ],
                [
                    'postId' => 6,
                    'title' => 'After Fajr Tea',
                    'description' => '<p>' . str_repeat('Tea after Fajr. ', 40) . '</p>',
                    'publishedAt' => '2026-10-26T17:15:00+00:00',
                    'image' => null,
                ],
            ],
        ];

        $html = $this->render_preview_tab();

        // Rendered HTML is stripped before it reaches the browser; esc_html
        // escapes the surviving entity.
        $this->assertStringContainsString('We&amp;rsquo;re Hiring', $html);
        $this->assertStringContainsString('Alhamdulillah, we are growing', $html);
        $this->assertStringContainsString('https://example.test/a.jpg', $html);
        // Long excerpts are truncated like the app's notification body
        // (180 characters plus the ellipsis).
        $this->assertMatchesRegularExpression('/Tea after Fajr\. [^<]{0,10}…/u', $html);
        $this->assertStringContainsString('masjidfeed-preview__announcement', $html);
    }

    public function test_preview_tab_renders_empty_state_without_announcements(): void {
        $html = $this->render_preview_tab();

        $this->assertStringContainsString('No announcements yet.', $html);
    }

    public function test_preview_tab_does_not_fetch_over_ajax(): void {
        $html = $this->render_preview_tab();

        $this->assertStringNotContainsString('admin-ajax.php', $html);
        $this->assertStringNotContainsString('masjidfeed_preview', $html);
    }

    public function test_preview_js_ships_the_schedule_and_stays_inert_off_the_tab(): void {
        $method = new ReflectionMethod(Masjid_Feed_Settings::class, 'preview_js');
        $js = $method->invoke(new Masjid_Feed_Settings());

        // The fake schedule mirrors the placeholder times rendered in PHP.
        $this->assertStringContainsString('Dhuhr', $js);
        // The script is inert off the Preview tab.
        $this->assertStringContainsString("if (!document.getElementById('masjidfeed-preview-root'))", $js);
        // The clock and countdown still tick.
        $this->assertStringContainsString('masjidfeed-preview-clock', $js);
        $this->assertStringContainsString('masjidfeed-preview-countdown', $js);
        // No AJAX roundtrip: data is rendered server-side.
        $this->assertStringNotContainsString('admin-ajax.php', $js);
        $this->assertStringNotContainsString('masjidfeed_preview', $js);
    }

    public function test_preview_tab_is_reachable_from_the_settings_page(): void {
        $html = $this->render_settings_page('preview');

        $this->assertStringContainsString('masjidfeed-preview-root', $html);
    }
}
