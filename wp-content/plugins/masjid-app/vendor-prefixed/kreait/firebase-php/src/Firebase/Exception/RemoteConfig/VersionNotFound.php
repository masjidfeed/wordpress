<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\RemoteConfig;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\RemoteConfigException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
use Masjid_App\Dependencies\Kreait\Firebase\RemoteConfig\VersionNumber;
final class VersionNotFound extends RuntimeException implements RemoteConfigException
{
    public static function withVersionNumber(VersionNumber $versionNumber): self
    {
        return new self('Version #' . $versionNumber . ' could not be found.');
    }
}
