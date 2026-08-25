<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable\ExpectedClosingParenthesisAfterCallable;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable\ExpectedColonAfterCallableClosingParenthesis;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable\ExpectedReturnTypeAfterCallableColon;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable\ExpectedTypeForCallable;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable\UnexpectedTokenAfterCallableClosingParenthesis;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\CallableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
/** @internal */
final class CallableToken implements TraversingToken
{
    use IsSingleton;
    public function traverse(TokenStream $stream): Type
    {
        if ($stream->done() || !$stream->next() instanceof OpeningParenthesisToken) {
            return CallableType::default();
        }
        $stream->forward();
        if ($stream->done()) {
            throw new ExpectedTypeForCallable();
        }
        $parameters = [];
        while (!$stream->next() instanceof ClosingParenthesisToken) {
            $parameters[] = $stream->read();
            if (!$stream->done() && $stream->next() instanceof CommaToken) {
                $stream->forward();
            }
            if ($stream->done()) {
                throw new ExpectedClosingParenthesisAfterCallable($parameters);
            }
        }
        $stream->forward();
        if ($stream->done()) {
            throw new ExpectedColonAfterCallableClosingParenthesis($parameters);
        }
        if (!($next = $stream->forward()) instanceof ColonToken) {
            throw new UnexpectedTokenAfterCallableClosingParenthesis($parameters, $next);
        }
        if ($stream->done()) {
            throw new ExpectedReturnTypeAfterCallableColon($parameters);
        }
        $returnType = $stream->read();
        return new CallableType($parameters, $returnType);
    }
    public function symbol(): string
    {
        return 'callable';
    }
}
