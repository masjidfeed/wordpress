<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class ArrayCommaMissing extends RuntimeException implements InvalidType
{
    public function __construct(string $symbol, Type $type)
    {
        $signature = "{$symbol}<{$type->toString()}, ?>";
        parent::__construct("A comma is missing for `{$signature}`.");
    }
}
