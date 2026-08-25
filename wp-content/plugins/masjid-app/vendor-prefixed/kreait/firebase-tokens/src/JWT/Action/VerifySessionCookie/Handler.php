<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\VerifySessionCookie;

use Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\VerifySessionCookie;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Token;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Error\SessionCookieVerificationFailed;
/**
 * @see https://firebase.google.com/docs/auth/admin/manage-cookies#verify_session_cookies_using_a_third-party_jwt_library
 */
interface Handler
{
    /**
     * @throws SessionCookieVerificationFailed
     */
    public function handle(VerifySessionCookie $action): Token;
}
