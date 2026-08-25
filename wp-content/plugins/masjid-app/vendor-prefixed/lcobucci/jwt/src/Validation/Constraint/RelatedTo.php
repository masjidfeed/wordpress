<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Masjid_App\Dependencies\Lcobucci\JWT\Token;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
final class RelatedTo implements Constraint
{
    /** @param non-empty-string $subject */
    public function __construct(private readonly string $subject)
    {
    }
    public function assert(Token $token): void
    {
        if (!$token->isRelatedTo($this->subject)) {
            throw ConstraintViolation::error('The token is not related to the expected subject', $this);
        }
    }
}
