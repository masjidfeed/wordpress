<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use RuntimeException;
/** @internal */
final class ShapedArrayCommaMissing extends RuntimeException implements InvalidType
{
    public function __construct(string $signature)
    {
        parent::__construct("Missing comma in `{$signature}`.");
    }
}
