<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\MessageBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\StringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\call;
use function is_numeric;
use function is_string;
/** @internal */
final class NumericStringType implements StringType
{
    use IsSingleton;
    public function accepts(mixed $value): bool
    {
        return is_string($value) && is_numeric($value);
    }
    public function compiledAccept(Node $node): Node
    {
        return call('is_string', [$node])->and(call('is_numeric', [$node]));
    }
    public function matches(Type $other): bool
    {
        if ($other instanceof UnionType) {
            return $other->isMatchedBy($this);
        }
        if ($other instanceof ArrayKeyType) {
            return $other->isMatchedBy($this);
        }
        return $other instanceof self || $other instanceof NativeStringType || $other instanceof NonEmptyStringType || $other instanceof ScalarConcreteType || $other instanceof MixedType;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function errorMessage(): ErrorMessage
    {
        return MessageBuilder::newError('Value {source_value} is not a valid numeric string.')->withCode('invalid_numeric_string')->build();
    }
    public function nativeType(): NativeStringType
    {
        return NativeStringType::get();
    }
    public function toString(): string
    {
        return 'numeric-string';
    }
}
