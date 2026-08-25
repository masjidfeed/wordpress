<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayElement;
use RuntimeException;
use function array_map;
use function implode;
/** @internal */
final class InvalidShapedArrayUnsealedType extends RuntimeException implements InvalidType
{
    public function __construct(Type $unsealedType, ShapedArrayElement ...$elements)
    {
        $elements = array_map(static fn(ShapedArrayElement $element) => $element->toString(), $elements);
        $elementsSignature = implode(', ', $elements);
        $signature = 'array{' . $elementsSignature . ", ...{$unsealedType->toString()}}";
        parent::__construct("Invalid unsealed type in shaped array `{$signature}`, it should be a valid array but `{$unsealedType->toString()}` was given.");
    }
}
