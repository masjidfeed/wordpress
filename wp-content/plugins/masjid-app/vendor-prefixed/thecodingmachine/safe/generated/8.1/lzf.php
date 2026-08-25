<?php

namespace Masjid_App\Dependencies\Safe;

use Masjid_App\Dependencies\Safe\Exceptions\LzfException;
/**
 * @param string $data
 * @return string
 * @throws LzfException
 *
 */
function lzf_compress(string $data): string
{
    error_clear_last();
    $safeResult = \lzf_compress($data);
    if ($safeResult === \false) {
        throw LzfException::createFromPhpError();
    }
    return $safeResult;
}
/**
 * @param string $data
 * @return string
 * @throws LzfException
 *
 */
function lzf_decompress(string $data): string
{
    error_clear_last();
    $safeResult = \lzf_decompress($data);
    if ($safeResult === \false) {
        throw LzfException::createFromPhpError();
    }
    return $safeResult;
}
