<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class KeyOfClosingBracketMissing extends RuntimeException implements InvalidType
{
    public function __construct(Type $type)
    {
        parent::__construct("The closing bracket is missing for `key-of<{$type->toString()}>`.");
    }
}
