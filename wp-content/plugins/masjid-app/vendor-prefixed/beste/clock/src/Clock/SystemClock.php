<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Beste\Clock;

use Masjid_App\Dependencies\Beste\Clock;
use DateTimeImmutable;
final class SystemClock implements Clock
{
    private function __construct()
    {
    }
    public static function create(): self
    {
        return new self();
    }
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }
}
