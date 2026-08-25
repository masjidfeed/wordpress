<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Token;

use InvalidArgumentException;
use Masjid_App\Dependencies\Lcobucci\JWT\Exception;
final class UnsupportedHeaderFound extends InvalidArgumentException implements Exception
{
    public static function encryption(): self
    {
        return new self('Encryption is not supported yet');
    }
}
