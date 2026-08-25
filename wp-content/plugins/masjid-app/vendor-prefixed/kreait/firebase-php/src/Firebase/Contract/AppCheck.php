<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckToken;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\AppCheckTokenOptions;
use Masjid_App\Dependencies\Kreait\Firebase\AppCheck\VerifyAppCheckTokenResponse;
use Masjid_App\Dependencies\Kreait\Firebase\Exception;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheck\FailedToVerifyAppCheckToken;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheck\InvalidAppCheckToken;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheck\InvalidAppCheckTokenOptions;
/**
 * @phpstan-import-type AppCheckTokenOptionsShape from AppCheckTokenOptions
 */
interface AppCheck
{
    /**
     * @param non-empty-string $appId
     * @param AppCheckTokenOptions|AppCheckTokenOptionsShape|null $options
     *
     * @throws InvalidAppCheckTokenOptions
     * @throws Exception\AppCheckException
     * @throws Exception\FirebaseException
     */
    public function createToken(string $appId, $options = null): AppCheckToken;
    /**
     * @param non-empty-string $appCheckToken
     *
     * @throws InvalidAppCheckToken
     * @throws FailedToVerifyAppCheckToken
     * @throws Exception\AppCheckException
     * @throws Exception\FirebaseException
     */
    public function verifyToken(string $appCheckToken): VerifyAppCheckTokenResponse;
}
