<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\MissingClosingQuoteChar;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\StringValueType;
/** @internal */
final class StringValueToken implements TraversingToken
{
    public function __construct(private string $value)
    {
    }
    public function traverse(TokenStream $stream): Type
    {
        $quoteType = $this->value[0];
        if ($this->value[-1] !== $quoteType) {
            throw new MissingClosingQuoteChar($this->value);
        }
        return StringValueType::quoted($this->value);
    }
    public function symbol(): string
    {
        return $this->value;
    }
}
