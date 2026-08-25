<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Scalar;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntegerValueType;
use RuntimeException;
/** @internal */
final class IntegerRangeMissingClosingBracket extends RuntimeException implements InvalidType
{
    public function __construct(IntegerValueType $min, IntegerValueType $max)
    {
        parent::__construct("Missing closing bracket in integer range signature `int<{$min->value()}, {$max->value()}>`.");
    }
}
