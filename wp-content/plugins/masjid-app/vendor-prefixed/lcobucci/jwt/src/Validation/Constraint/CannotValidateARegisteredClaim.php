<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;

use InvalidArgumentException;
use Masjid_App\Dependencies\Lcobucci\JWT\Exception;
final class CannotValidateARegisteredClaim extends InvalidArgumentException implements Exception
{
    /** @param non-empty-string $claim */
    public static function create(string $claim): self
    {
        return new self('The claim "' . $claim . '" is a registered claim, another constraint must be used to validate its value');
    }
}
