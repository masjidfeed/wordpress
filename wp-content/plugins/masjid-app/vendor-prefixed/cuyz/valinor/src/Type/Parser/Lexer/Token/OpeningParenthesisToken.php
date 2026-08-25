<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\ExpectedClosingParenthesisAfterType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\UnexpectedToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
/** @internal */
final class OpeningParenthesisToken implements TraversingToken
{
    use IsSingleton;
    public function traverse(TokenStream $stream): Type
    {
        if ($stream->done()) {
            throw new UnexpectedToken('(');
        }
        $type = $stream->read();
        if ($stream->done()) {
            throw new ExpectedClosingParenthesisAfterType($type->toString());
        }
        $next = $stream->forward();
        if (!$next instanceof ClosingParenthesisToken) {
            throw new UnexpectedToken($next->symbol());
        }
        return $type;
    }
    public function symbol(): string
    {
        return '(';
    }
}
