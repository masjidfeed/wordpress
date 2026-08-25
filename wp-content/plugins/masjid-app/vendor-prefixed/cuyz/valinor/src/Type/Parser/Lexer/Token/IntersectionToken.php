<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Intersection\RightIntersectionTypeMissing;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntersectionType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
/** @internal */
final class IntersectionToken implements LeftTraversingToken
{
    use IsSingleton;
    public function traverse(Type $type, TokenStream $stream): Type
    {
        if ($stream->done()) {
            throw new RightIntersectionTypeMissing($type);
        }
        $rightType = $stream->read();
        if ($rightType instanceof IntersectionType) {
            return IntersectionType::from($type, ...$rightType->types());
        }
        return IntersectionType::from($type, $rightType);
    }
    public function symbol(): string
    {
        return '&';
    }
}
