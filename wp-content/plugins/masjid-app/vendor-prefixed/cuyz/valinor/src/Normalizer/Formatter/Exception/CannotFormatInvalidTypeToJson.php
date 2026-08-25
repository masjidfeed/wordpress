<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Formatter\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Configurator\IgnoreOnNormalization;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception\IgnoreOnNormalizationIsNotRegistered;
use RuntimeException;
use function get_debug_type;
/** @internal */
final class CannotFormatInvalidTypeToJson extends RuntimeException
{
    public function __construct(mixed $value)
    {
        if ($value instanceof IgnoreOnNormalization) {
            throw new IgnoreOnNormalizationIsNotRegistered();
        }
        $type = get_debug_type($value);
        parent::__construct("Value of type `{$type}` cannot be normalized to JSON.");
    }
}
