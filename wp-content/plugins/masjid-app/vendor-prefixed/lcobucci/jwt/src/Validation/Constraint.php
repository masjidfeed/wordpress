<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation;

use Masjid_App\Dependencies\Lcobucci\JWT\Token;
interface Constraint
{
    /** @throws ConstraintViolation */
    public function assert(Token $token): void;
}
