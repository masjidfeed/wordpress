<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\Token;
/** @internal */
interface TypeLexer
{
    public function tokenize(string $symbol): Token;
}
