<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntegerValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\StringValueType;
use RuntimeException;
use function str_ends_with;
/** @internal */
final class ShapedArrayElementTypeMissing extends RuntimeException implements InvalidType
{
    public function __construct(string $signature, StringValueType|IntegerValueType $key, bool $optional)
    {
        if (!str_ends_with($signature, '{')) {
            $signature .= ', ';
        }
        $signature .= $key->value();
        if ($optional) {
            $signature .= '?';
        }
        $signature .= ':';
        parent::__construct("Missing element type in `{$signature}`.");
    }
}
