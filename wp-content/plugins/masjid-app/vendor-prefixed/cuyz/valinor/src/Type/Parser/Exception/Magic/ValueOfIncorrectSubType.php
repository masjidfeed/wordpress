<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class ValueOfIncorrectSubType extends RuntimeException implements InvalidType
{
    public function __construct(Type $type)
    {
        parent::__construct("Invalid subtype `value-of<{$type->toString()}>`, it should be a `BackedEnum`, `array`, `list` or `iterable`.");
    }
}
