<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\CalendarException;
/**
 * @param int|null $timestamp
 * @return int
 * @throws CalendarException
 *
 */
function unixtojd(?int $timestamp = null): int
{
    error_clear_last();
    if ($timestamp !== null) {
        $safeResult = \unixtojd($timestamp);
    } else {
        $safeResult = \unixtojd();
    }
    if ($safeResult === \false) {
        throw CalendarException::createFromPhpError();
    }
    return $safeResult;
}
