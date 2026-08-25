<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract\Transitional;

use Masjid_App\Dependencies\Kreait\Firebase\Auth\UserRecord;
use Masjid_App\Dependencies\Kreait\Firebase\Exception;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\Auth\UserNotFound;
use Stringable;
/**
 * @TODO: This interface is intended to be integrated into the Auth interface on the next major release.
 */
interface FederatedUserFetcher
{
    /**
     * @param Stringable|non-empty-string $providerId
     * @param Stringable|non-empty-string $providerUid
     *
     * @throws UserNotFound
     * @throws Exception\AuthException
     * @throws Exception\FirebaseException
     */
    public function getUserByProviderUid(Stringable|string $providerId, Stringable|string $providerUid): UserRecord;
}
