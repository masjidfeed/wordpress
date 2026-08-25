<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Auth;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\AuthException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Masjid_App\Dependencies\Lcobucci\JWT\Token;
final class RevokedIdToken extends RuntimeException implements AuthException
{
    public function __construct(private readonly Token $token)
    {
        parent::__construct('The Firebase ID token has been revoked.');
    }
    public function getToken(): Token
    {
        return $this->token;
    }
}
