<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class ShapedArrayWithoutElementsWithSealedType extends RuntimeException implements InvalidType
{
    public function __construct(string $signature, Type $unsealedType)
    {
        $signature .= '...' . $unsealedType->toString() . '}';
        parent::__construct("Missing elements in `{$signature}`.");
    }
}
