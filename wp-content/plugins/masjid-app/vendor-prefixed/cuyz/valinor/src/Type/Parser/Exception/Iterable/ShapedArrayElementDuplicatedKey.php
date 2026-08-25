<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use RuntimeException;
/** @internal */
final class ShapedArrayElementDuplicatedKey extends RuntimeException implements InvalidType
{
    public function __construct(string $key)
    {
        parent::__construct("Key `{$key}` cannot be used several times in shaped array.");
    }
}
