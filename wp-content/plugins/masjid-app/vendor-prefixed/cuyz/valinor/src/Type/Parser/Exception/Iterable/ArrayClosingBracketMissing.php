<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class ArrayClosingBracketMissing extends RuntimeException implements InvalidType
{
    public function __construct(Type $arrayType)
    {
        parent::__construct("The closing bracket is missing for `{$arrayType->toString()}`.");
    }
}
