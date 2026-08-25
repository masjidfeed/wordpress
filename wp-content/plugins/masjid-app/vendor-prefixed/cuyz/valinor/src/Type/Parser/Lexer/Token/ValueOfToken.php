<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token;

use BackedEnum;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\CompositeTraversableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic\ValueOfClosingBracketMissing;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic\ValueOfIncorrectSubType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic\ValueOfMissingSubType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Magic\ValueOfOpeningBracketMissing;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\TokenStream;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\EnumType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\Factory\ValueTypeFactory;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnionType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
use function array_map;
use function array_values;
use function count;
use function is_a;
/** @internal */
final class ValueOfToken implements TraversingToken
{
    use IsSingleton;
    public function traverse(TokenStream $stream): Type
    {
        if ($stream->done() || !$stream->forward() instanceof OpeningBracketToken) {
            throw new ValueOfOpeningBracketMissing();
        }
        if ($stream->done()) {
            throw new ValueOfMissingSubType();
        }
        $subType = $stream->read();
        if ($stream->done() || !$stream->forward() instanceof ClosingBracketToken) {
            throw new ValueOfClosingBracketMissing($subType);
        }
        if ($subType instanceof EnumType && is_a($subType->className(), BackedEnum::class, \true)) {
            $cases = array_map(
                // @phpstan-ignore-next-line / We know it's a BackedEnum
                fn(BackedEnum $case) => ValueTypeFactory::from($case->value),
                array_values($subType->cases())
            );
            if (count($cases) > 1) {
                return UnionType::from(...$cases);
            }
            return $cases[0];
        }
        if ($subType instanceof ShapedArrayType || $subType instanceof ShapedListType) {
            $types = array_map(fn($element) => $element->type(), array_values($subType->elements));
            if (count($types) > 1) {
                return UnionType::from(...$types);
            }
            return $types[0];
        }
        if ($subType instanceof CompositeTraversableType) {
            return $subType->subType();
        }
        throw new ValueOfIncorrectSubType($subType);
    }
    public function symbol(): string
    {
        return 'value-of';
    }
}
