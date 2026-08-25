<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Signer;

use Masjid_App\Dependencies\Lcobucci\JWT\Signer;
use Masjid_App\Dependencies\Lcobucci\JWT\Signer\Key;
final class None implements Signer
{
    public function algorithmId(): string
    {
        return 'none';
    }
    public function sign(string $payload, Key $key): string
    {
        // @phpstan-ignore-next-line
        return '';
    }
    public function verify(string $expected, string $payload, Key $key): bool
    {
        // @phpstan-ignore-next-line
        return $expected === '';
    }
}
