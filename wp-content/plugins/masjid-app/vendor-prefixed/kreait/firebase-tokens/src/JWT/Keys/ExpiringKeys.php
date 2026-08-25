<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Keys;

use DateTimeImmutable;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Expirable;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\ExpirableTrait;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Keys;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\KeysTrait;
/**
 * @internal
 */
final class ExpiringKeys implements Expirable, Keys
{
    use KeysTrait;
    use ExpirableTrait;
    private function __construct()
    {
        $this->expirationTime = new DateTimeImmutable('0001-01-01');
        // Very distant past :)
    }
    /**
     * @param array<non-empty-string, non-empty-string> $values
     */
    public static function withValuesAndExpirationTime(array $values, DateTimeImmutable $expirationTime): self
    {
        $keys = new self();
        $keys->values = $values;
        $keys->expirationTime = $expirationTime;
        return $keys;
    }
}
