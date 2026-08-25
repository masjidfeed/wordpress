<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\ClassobjException;
/**
 * @param string $class
 * @param string $alias
 * @param bool $autoload
 * @throws ClassobjException
 *
 */
function class_alias(string $class, string $alias, bool $autoload = \true): void
{
    error_clear_last();
    $safeResult = \class_alias($class, $alias, $autoload);
    if ($safeResult === \false) {
        throw ClassobjException::createFromPhpError();
    }
}
