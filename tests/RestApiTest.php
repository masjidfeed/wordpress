<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

class RestApiTest extends TestCase {

    public function test_resolve_list_limit_defaults_to_20(): void {
        $this->assertSame(20, Masjid_Feed_REST_API::resolve_list_limit(null));
        $this->assertSame(20, Masjid_Feed_REST_API::resolve_list_limit(0));
        $this->assertSame(20, Masjid_Feed_REST_API::resolve_list_limit('not-a-number'));
    }

    public function test_resolve_list_limit_accepts_values_within_range(): void {
        $this->assertSame(1, Masjid_Feed_REST_API::resolve_list_limit(1));
        $this->assertSame(50, Masjid_Feed_REST_API::resolve_list_limit(50));
        $this->assertSame(100, Masjid_Feed_REST_API::resolve_list_limit('100'));
    }

    public function test_resolve_list_limit_caps_values_above_100(): void {
        $this->assertSame(100, Masjid_Feed_REST_API::resolve_list_limit(101));
        $this->assertSame(100, Masjid_Feed_REST_API::resolve_list_limit(5000));
    }
}
