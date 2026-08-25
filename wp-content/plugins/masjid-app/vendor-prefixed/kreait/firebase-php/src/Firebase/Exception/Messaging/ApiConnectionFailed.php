<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\HasErrors;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\MessagingException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\RuntimeException;
final class ApiConnectionFailed extends RuntimeException implements MessagingException
{
    use HasErrors;
}
