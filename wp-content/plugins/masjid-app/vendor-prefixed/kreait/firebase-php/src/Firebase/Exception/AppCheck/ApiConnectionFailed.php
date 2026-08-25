<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheck;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\AppCheckException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\HasErrors;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class ApiConnectionFailed extends RuntimeException implements AppCheckException
{
    use HasErrors;
}
