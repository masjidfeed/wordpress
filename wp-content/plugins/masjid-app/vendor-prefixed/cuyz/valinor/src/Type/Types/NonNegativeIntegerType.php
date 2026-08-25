<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\MessageBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\IntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\call;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
use function is_int;
/** @internal */
final class NonNegativeIntegerType implements IntegerType
{
    use IsSingleton;
    public function accepts(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }
    public function compiledAccept(Node $node): Node
    {
        return call('is_int', [$node])->and($node->isGreaterOrEqualsTo(value(0)));
    }
    public function matches(Type $other): bool
    {
        if ($other instanceof UnionType) {
            return $other->isMatchedBy($this);
        }
        if ($other instanceof ArrayKeyType) {
            return $other->isMatchedBy($this);
        }
        return $other instanceof self || $other instanceof NativeIntegerType || $other instanceof ScalarConcreteType || $other instanceof MixedType;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function errorMessage(): ErrorMessage
    {
        return MessageBuilder::newError('Value {source_value} is not a valid non-negative integer.')->withCode('invalid_non_negative_integer')->build();
    }
    public function nativeType(): NativeIntegerType
    {
        return NativeIntegerType::get();
    }
    public function toString(): string
    {
        return 'non-negative-int';
    }
}
