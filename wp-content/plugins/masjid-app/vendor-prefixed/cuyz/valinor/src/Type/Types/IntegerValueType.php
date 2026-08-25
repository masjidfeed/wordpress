<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\MessageBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\FixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\IntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
/** @internal */
final class IntegerValueType implements IntegerType, FixedType
{
    public function __construct(private int $value)
    {
    }
    public function accepts(mixed $value): bool
    {
        return $value === $this->value;
    }
    public function compiledAccept(Node $node): Node
    {
        return $node->equals(value($this->value));
    }
    public function matches(Type $other): bool
    {
        return $other->accepts($this->value);
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function errorMessage(): ErrorMessage
    {
        return MessageBuilder::newError('Value {source_value} does not match integer value {expected_value}.')->withCode('invalid_integer_value')->withParameter('expected_value', (string) $this->value)->build();
    }
    public function value(): int
    {
        return $this->value;
    }
    public function nativeType(): NativeIntegerType
    {
        return NativeIntegerType::get();
    }
    /**
     * @return non-empty-string
     */
    public function toString(): string
    {
        return (string) $this->value;
    }
}
