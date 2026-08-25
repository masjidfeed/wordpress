<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT;

use Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\NoConstraintsGiven;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\RequiredConstraintsViolated;
interface Validator
{
    /**
     * @throws RequiredConstraintsViolated
     * @throws NoConstraintsGiven
     */
    public function assert(Token $token, Constraint ...$constraints): void;
    /** @throws NoConstraintsGiven */
    public function validate(Token $token, Constraint ...$constraints): bool;
}
