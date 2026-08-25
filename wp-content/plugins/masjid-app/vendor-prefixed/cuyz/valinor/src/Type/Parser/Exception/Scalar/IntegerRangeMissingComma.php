<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Scalar;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntegerValueType;
use RuntimeException;
/** @internal */
final class IntegerRangeMissingComma extends RuntimeException implements InvalidType
{
    public function __construct(IntegerValueType $min)
    {
        parent::__construct("Missing comma in integer range signature `int<{$min->value()}, ?>`.");
    }
}
