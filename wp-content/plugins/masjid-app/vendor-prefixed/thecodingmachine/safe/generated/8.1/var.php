<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\VarException;
/**
 * @param mixed $var
 * @param string $type
 * @throws VarException
 *
 */
function settype(&$var, string $type): void
{
    error_clear_last();
    $safeResult = \settype($var, $type);
    if ($safeResult === \false) {
        throw VarException::createFromPhpError();
    }
}
