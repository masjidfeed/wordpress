<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception\Messaging;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\HasErrors;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\MessagingException;
final class InvalidArgument extends InvalidArgumentException implements MessagingException
{
    use HasErrors;
}
