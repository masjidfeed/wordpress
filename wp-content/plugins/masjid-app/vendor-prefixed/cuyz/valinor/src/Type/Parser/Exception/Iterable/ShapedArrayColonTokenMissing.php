<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
use function str_ends_with;
/** @internal */
final class ShapedArrayColonTokenMissing extends RuntimeException implements InvalidType
{
    public function __construct(string $signature, Type $type)
    {
        if (!str_ends_with($signature, '{')) {
            $signature .= ', ';
        }
        $signature .= "{$type->toString()}?";
        parent::__construct("Missing colon in `{$signature}`.");
    }
}
