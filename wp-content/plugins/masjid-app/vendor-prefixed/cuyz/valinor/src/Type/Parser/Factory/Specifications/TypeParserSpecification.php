<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Factory\Specifications;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\TraversingToken;
/** @internal */
interface TypeParserSpecification
{
    public function manipulateToken(TraversingToken $token): TraversingToken;
}
