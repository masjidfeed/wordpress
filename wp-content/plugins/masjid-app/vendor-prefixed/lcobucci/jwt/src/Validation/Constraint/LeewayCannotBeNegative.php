<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;

use InvalidArgumentException;
use Masjid_App\Dependencies\Lcobucci\JWT\Exception;
final class LeewayCannotBeNegative extends InvalidArgumentException implements Exception
{
    public static function create(): self
    {
        return new self('Leeway cannot be negative');
    }
}
