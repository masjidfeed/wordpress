<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Union\RightUnionTypeMissing;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnionType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
/** @internal */
final class UnionToken implements LeftTraversingToken
{
    use IsSingleton;
    public function traverse(Type $type, TokenStream $stream): Type
    {
        if ($stream->done()) {
            throw new RightUnionTypeMissing($type);
        }
        return UnionType::from($type, $stream->read());
    }
    public function symbol(): string
    {
        return '|';
    }
}
