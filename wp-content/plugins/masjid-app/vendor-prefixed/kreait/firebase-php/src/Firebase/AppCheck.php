<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase;

use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\ApiClient;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckToken;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckTokenGenerator;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckTokenOptions;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckTokenVerifier;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\VerifyAppCheckTokenResponse;
use function is_array;
/**
 * @internal
 */
final class AppCheck implements Contract\AppCheck
{
    public function __construct(private readonly ApiClient $client, private readonly AppCheckTokenGenerator $tokenGenerator, private readonly AppCheckTokenVerifier $tokenVerifier)
    {
    }
    public function createToken(string $appId, $options = null): AppCheckToken
    {
        if (is_array($options)) {
            $options = AppCheckTokenOptions::fromArray($options);
        }
        $customToken = $this->tokenGenerator->createCustomToken($appId, $options);
        $result = $this->client->exchangeCustomToken($appId, $customToken);
        return AppCheckToken::fromArray($result);
    }
    public function verifyToken(string $appCheckToken): VerifyAppCheckTokenResponse
    {
        $decodedToken = $this->tokenVerifier->verifyToken($appCheckToken);
        return new VerifyAppCheckTokenResponse($decodedToken->app_id, $decodedToken);
    }
}
