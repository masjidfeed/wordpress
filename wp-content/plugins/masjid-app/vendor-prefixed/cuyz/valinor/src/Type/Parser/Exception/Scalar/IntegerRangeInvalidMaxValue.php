<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Scalar;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntegerValueType;
use RuntimeException;
/** @internal */
final class IntegerRangeInvalidMaxValue extends RuntimeException implements InvalidType
{
    public function __construct(IntegerValueType $min, Type $type)
    {
        parent::__construct("Invalid type `{$type->toString()}` for max value of integer range `int<{$min->value()}, ?>`, it must be either `max` or an integer value.");
    }
}
