<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Auth;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\AuthException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class EmailNotFound extends RuntimeException implements AuthException
{
}
