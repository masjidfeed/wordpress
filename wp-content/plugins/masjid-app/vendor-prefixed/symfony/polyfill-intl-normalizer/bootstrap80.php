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
use Masjid_App\Dependencies\Symfony\Polyfill\Intl\Normalizer as p;
if (!\function_exists('normalizer_is_normalized') && !\function_exists('Masjid_App\Dependencies\normalizer_is_normalized')) {
    function normalizer_is_normalized(?string $string, ?int $form = p\Normalizer::FORM_C): bool
    {
        return p\Normalizer::isNormalized((string) $string, (int) $form);
    }
}
if (!\function_exists('normalizer_normalize') && !\function_exists('Masjid_App\Dependencies\normalizer_normalize')) {
    function normalizer_normalize(?string $string, ?int $form = p\Normalizer::FORM_C): string|false
    {
        return p\Normalizer::normalize((string) $string, (int) $form);
    }
}
if (!\function_exists('normalizer_get_raw_decomposition') && !\function_exists('Masjid_App\Dependencies\normalizer_get_raw_decomposition')) {
    function normalizer_get_raw_decomposition(?string $string, ?int $form = p\Normalizer::FORM_C): ?string
    {
        return p\Normalizer::getRawDecomposition((string) $string, (int) $form);
    }
}
