<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Factory\Specifications\TypeParserSpecification;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\Token;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\VacantToken;
/** @internal */
final class SpecificationsLexer implements TypeLexer
{
    public function __construct(
        /** @var array<TypeParserSpecification> */
        private array $specifications
    )
    {
    }
    public function tokenize(string $symbol): Token
    {
        return new VacantToken($symbol, $this->specifications);
    }
}
