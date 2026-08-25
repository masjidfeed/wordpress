<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract;

use DateTimeImmutable;
use DateTimeInterface;
interface Expirable
{
    public function withExpirationTime(DateTimeImmutable $time): self;
    public function isExpiredAt(DateTimeInterface $now): bool;
    public function expiresAt(): DateTimeImmutable;
}
