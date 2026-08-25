<?php

namespace Masjid_App\Dependencies;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
use Masjid_App\Dependencies\Symfony\Polyfill\Php84 as p;
if (\extension_loaded('mbstring')) {
    if (!\function_exists('mb_ucfirst') && !\function_exists('Masjid_App\Dependencies\mb_ucfirst')) {
        /** @return string|false */
        function mb_ucfirst(?string $string, ?string $encoding = null)
        {
            return p\Php84::mb_ucfirst((string) $string, $encoding);
        }
    }
    if (!\function_exists('mb_lcfirst') && !\function_exists('Masjid_App\Dependencies\mb_lcfirst')) {
        /** @return string|false */
        function mb_lcfirst(?string $string, ?string $encoding = null)
        {
            return p\Php84::mb_lcfirst((string) $string, $encoding);
        }
    }
    if (!\function_exists('mb_trim') && !\function_exists('Masjid_App\Dependencies\mb_trim')) {
        /** @return string|false */
        function mb_trim(?string $string, ?string $characters = null, ?string $encoding = null)
        {
            return p\Php84::mb_trim((string) $string, $characters, $encoding);
        }
    }
    if (!\function_exists('mb_ltrim') && !\function_exists('Masjid_App\Dependencies\mb_ltrim')) {
        /** @return string|false */
        function mb_ltrim(?string $string, ?string $characters = null, ?string $encoding = null)
        {
            return p\Php84::mb_ltrim((string) $string, $characters, $encoding);
        }
    }
    if (!\function_exists('mb_rtrim') && !\function_exists('Masjid_App\Dependencies\mb_rtrim')) {
        /** @return string|false */
        function mb_rtrim(?string $string, ?string $characters = null, ?string $encoding = null)
        {
            return p\Php84::mb_rtrim((string) $string, $characters, $encoding);
        }
    }
}
