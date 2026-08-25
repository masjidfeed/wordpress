<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\RemoteConfig;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\RemoteConfigException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class VersionMismatch extends RuntimeException implements RemoteConfigException
{
}
