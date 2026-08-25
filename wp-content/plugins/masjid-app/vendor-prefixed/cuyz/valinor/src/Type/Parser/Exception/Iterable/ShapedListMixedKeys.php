<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayElement;
use RuntimeException;
use function array_filter;
use function array_map;
use function implode;
/** @internal */
final class ShapedListMixedKeys extends RuntimeException implements InvalidType
{
    /**
     * @param ShapedArrayElement[] $elements
     */
    public function __construct(array $elements)
    {
        $hasOptional = array_filter($elements, fn(ShapedArrayElement $element) => $element->isOptional()) !== [];
        $parts = array_map(static fn(ShapedArrayElement $element) => $hasOptional ? $element->key()->value() . ($element->isOptional() ? '?: ' : ': ') . $element->type()->toString() : $element->type()->toString(), $elements);
        $signature = 'list{' . implode(', ', $parts) . '}';
        parent::__construct("Cannot mix explicit and implicit keys in shaped list `{$signature}`.");
    }
}
