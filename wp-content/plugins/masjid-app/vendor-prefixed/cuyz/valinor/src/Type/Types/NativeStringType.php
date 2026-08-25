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
use function is_string;
/** @internal */
final class NativeStringType implements StringType
{
    use IsSingleton;
    public function accepts(mixed $value): bool
    {
        return is_string($value);
    }
    public function compiledAccept(Node $node): Node
    {
        return call('is_string', [$node]);
    }
    public function matches(Type $other): bool
    {
        if ($other instanceof UnionType) {
            return $other->isMatchedBy($this);
        }
        if ($other instanceof ArrayKeyType) {
            return $other->isMatchedBy($this);
        }
        return $other instanceof self || $other instanceof ScalarConcreteType || $other instanceof MixedType;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function errorMessage(): ErrorMessage
    {
        return MessageBuilder::newError('Value {source_value} is not a valid string.')->withCode('invalid_string')->build();
    }
    public function nativeType(): NativeStringType
    {
        return $this;
    }
    public function toString(): string
    {
        return 'string';
    }
}
