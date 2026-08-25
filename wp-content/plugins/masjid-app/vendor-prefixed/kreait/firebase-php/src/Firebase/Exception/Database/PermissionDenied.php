<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Database;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\DatabaseException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class PermissionDenied extends RuntimeException implements DatabaseException
{
}
