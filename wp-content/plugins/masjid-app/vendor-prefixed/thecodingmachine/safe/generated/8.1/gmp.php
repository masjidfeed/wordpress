<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\GmpException;
/**
 * @param \GMP|int|string $seed
 * @throws GmpException
 *
 */
function gmp_random_seed($seed): void
{
    error_clear_last();
    $safeResult = \gmp_random_seed($seed);
    if ($safeResult === \false) {
        throw GmpException::createFromPhpError();
    }
}
