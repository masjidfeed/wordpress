<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation\Constraint;

use Masjid_App\Dependencies\Lcobucci\JWT\Signer;
use Masjid_App\Dependencies\Lcobucci\JWT\Token;
use Masjid_App\Dependencies\Lcobucci\JWT\UnencryptedToken;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\ConstraintViolation;
use Masjid_App\Dependencies\Lcobucci\JWT\Validation\SignedWith as SignedWithInterface;
final class SignedWith implements SignedWithInterface
{
    public function __construct(private readonly Signer $signer, private readonly Signer\Key $key)
    {
    }
    public function assert(Token $token): void
    {
        if (!$token instanceof UnencryptedToken) {
            throw ConstraintViolation::error('You should pass a plain token', $this);
        }
        if ($token->headers()->get('alg') !== $this->signer->algorithmId()) {
            throw ConstraintViolation::error('Token signer mismatch', $this);
        }
        if (!$this->signer->verify($token->signature()->hash(), $token->payload(), $this->key)) {
            throw ConstraintViolation::error('Token signature mismatch', $this);
        }
    }
}
