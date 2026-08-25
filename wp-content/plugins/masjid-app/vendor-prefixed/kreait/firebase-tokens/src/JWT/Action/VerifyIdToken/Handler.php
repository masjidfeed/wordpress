<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\VerifyIdToken;

use Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\VerifyIdToken;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Token;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Error\IdTokenVerificationFailed;
/**
 * @see https://firebase.google.com/docs/auth/admin/verify-id-tokens#verify_id_tokens_using_a_third-party_jwt_library
 */
interface Handler
{
    /**
     * @throws IdTokenVerificationFailed
     */
    public function handle(VerifyIdToken $action): Token;
}
