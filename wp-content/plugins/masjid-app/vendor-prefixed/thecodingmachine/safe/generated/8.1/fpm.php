<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\FpmException;
/**
 * @throws FpmException
 *
 */
function fastcgi_finish_request(): void
{
    error_clear_last();
    $safeResult = \fastcgi_finish_request();
    if ($safeResult === \false) {
        throw FpmException::createFromPhpError();
    }
}
