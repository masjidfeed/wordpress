<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Iterable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\Token;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
use function array_map;
use function implode;
/** @internal */
final class ShapedArrayUnexpectedTokenAfterSealedType extends RuntimeException implements InvalidType
{
    /**
     * @param list<Token> $unexpectedTokens
     */
    public function __construct(string $signature, Type $unsealedType, array $unexpectedTokens)
    {
        $unexpected = implode('', array_map(fn(Token $token) => $token->symbol(), $unexpectedTokens));
        $signature .= ', ...' . $unsealedType->toString() . $unexpected;
        parent::__construct("Unexpected `{$unexpected}` after sealed type in `{$signature}`.");
    }
}
