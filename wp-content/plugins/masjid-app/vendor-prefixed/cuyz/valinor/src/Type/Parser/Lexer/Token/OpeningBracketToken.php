<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
/** @internal */
final class OpeningBracketToken implements Token
{
    use IsSingleton;
    public function symbol(): string
    {
        return '<';
    }
}
