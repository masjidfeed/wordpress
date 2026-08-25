<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\ErrorHandler;

use Masjid_App\Dependencies\PhpParser\Error;
use Masjid_App\Dependencies\PhpParser\ErrorHandler;
/**
 * Error handler that handles all errors by throwing them.
 *
 * This is the default strategy used by all components.
 */
class Throwing implements ErrorHandler
{
    public function handleError(Error $error): void
    {
        throw $error;
    }
}
