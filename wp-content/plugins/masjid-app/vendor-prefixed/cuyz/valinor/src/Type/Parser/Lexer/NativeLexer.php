<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ArrayToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\CallableToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ClassStringToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ClosingBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ClosingCurlyBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ClosingParenthesisToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ClosingSquareBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ColonToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\CommaToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\DoubleColonToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\FloatValueToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\IntegerToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\IntegerValueToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\IntersectionToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\KeyOfToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\NullableToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\OpeningBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\OpeningCurlyBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\OpeningParenthesisToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\OpeningSquareBracketToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\StringValueToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\Token;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\TripleDotsToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\TypeToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\UnionToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Lexer\Token\ValueOfToken;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ArrayKeyType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\BooleanValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IterableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeBooleanType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeFloatType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeStringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NegativeIntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonEmptyArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonEmptyListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonEmptyStringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonNegativeIntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonPositiveIntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NullType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NumericStringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\PositiveIntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ScalarConcreteType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UndefinedObjectType;
use function filter_var;
use function is_numeric;
use function str_starts_with;
use function strtolower;
/** @internal */
final class NativeLexer implements TypeLexer
{
    public function __construct(private TypeLexer $delegate)
    {
    }
    public function tokenize(string $symbol): Token
    {
        return match (strtolower($symbol)) {
            '|' => UnionToken::get(),
            '&' => IntersectionToken::get(),
            '(' => OpeningParenthesisToken::get(),
            ')' => ClosingParenthesisToken::get(),
            '<' => OpeningBracketToken::get(),
            '>' => ClosingBracketToken::get(),
            '[' => OpeningSquareBracketToken::get(),
            ']' => ClosingSquareBracketToken::get(),
            '{' => OpeningCurlyBracketToken::get(),
            '}' => ClosingCurlyBracketToken::get(),
            '::' => DoubleColonToken::get(),
            ':' => ColonToken::get(),
            '?' => NullableToken::get(),
            ',' => CommaToken::get(),
            '...' => TripleDotsToken::get(),
            'int', 'integer' => IntegerToken::get(),
            'array' => new ArrayToken('array', ArrayType::class),
            'non-empty-array' => new ArrayToken('non-empty-array', NonEmptyArrayType::class),
            'iterable' => new ArrayToken('iterable', IterableType::class),
            'list' => new ArrayToken('list', ListType::class),
            'non-empty-list' => new ArrayToken('non-empty-list', NonEmptyListType::class),
            'class-string' => ClassStringToken::get(),
            'callable' => CallableToken::get(),
            'value-of' => ValueOfToken::get(),
            'key-of' => KeyOfToken::get(),
            'null' => new TypeToken(NullType::get()),
            'true' => new TypeToken(BooleanValueType::true()),
            'false' => new TypeToken(BooleanValueType::false()),
            'mixed' => new TypeToken(MixedType::get()),
            'float' => new TypeToken(NativeFloatType::get()),
            'positive-int' => new TypeToken(PositiveIntegerType::get()),
            'negative-int' => new TypeToken(NegativeIntegerType::get()),
            'non-positive-int' => new TypeToken(NonPositiveIntegerType::get()),
            'non-negative-int' => new TypeToken(NonNegativeIntegerType::get()),
            'string' => new TypeToken(NativeStringType::get()),
            'non-empty-string' => new TypeToken(NonEmptyStringType::get()),
            'numeric-string' => new TypeToken(NumericStringType::get()),
            'bool', 'boolean' => new TypeToken(NativeBooleanType::get()),
            'array-key' => new TypeToken(ArrayKeyType::default()),
            'object' => new TypeToken(UndefinedObjectType::get()),
            'scalar' => new TypeToken(ScalarConcreteType::get()),
            default => match (\true) {
                str_starts_with($symbol, "'") || str_starts_with($symbol, '"') => new StringValueToken($symbol),
                filter_var($symbol, \FILTER_VALIDATE_INT) !== \false => new IntegerValueToken((int) $symbol),
                is_numeric($symbol) => new FloatValueToken((float) $symbol),
                default => $this->delegate->tokenize($symbol),
            },
        };
    }
}
