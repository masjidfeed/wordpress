<?php

namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokensExtractor;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TypeLexer;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use function array_map;
/** @internal */
class LexingParser implements TypeParser
{
    public function __construct(private TypeLexer $lexer)
    {
    }
    public function parse(string $raw): Type
    {
        try {
            $tokens = array_map($this->lexer->tokenize(...), (new TokensExtractor($raw))->filtered());
            return (new TokenStream(...$tokens))->read();
        } catch (InvalidType $invalidType) {
            return new UnresolvableType($raw, $invalidType->getMessage());
        }
    }
}
