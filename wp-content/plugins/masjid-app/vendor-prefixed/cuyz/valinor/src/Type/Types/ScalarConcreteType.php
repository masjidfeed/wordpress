<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\MessageBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ScalarType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\call;
use function is_scalar;
/** @internal */
final class ScalarConcreteType implements ScalarType
{
    use IsSingleton;
    public function accepts(mixed $value): bool
    {
        return is_scalar($value);
    }
    public function compiledAccept(Node $node): Node
    {
        return call('is_scalar', [$node]);
    }
    public function matches(Type $other): bool
    {
        if ($other instanceof UnionType) {
            return (new UnionType(NativeIntegerType::get(), NativeFloatType::get(), NativeStringType::get(), NativeBooleanType::get()))->matches($other);
        }
        return $other instanceof self || $other instanceof MixedType;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function errorMessage(): ErrorMessage
    {
        return MessageBuilder::newError('Value {source_value} is not a valid scalar.')->build();
    }
    public function nativeType(): UnionType
    {
        return new UnionType(new NativeIntegerType(), new NativeFloatType(), new NativeStringType(), new NativeBooleanType());
    }
    public function toString(): string
    {
        return 'scalar';
    }
}
