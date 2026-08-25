<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\CompositeTraversableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\DumpableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Polyfill;
use function array_is_list;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\call;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\logicalAnd;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\param;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\shortClosure;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\variable;
use function is_array;
/** @internal */
final class NonEmptyListType implements CompositeTraversableType, DumpableType
{
    private static self $native;
    public function __construct(private Type $subType)
    {
    }
    public static function native(): self
    {
        return self::$native ??= new self(MixedType::get());
    }
    public function accepts(mixed $value): bool
    {
        if ($value === []) {
            return \false;
        }
        if (!is_array($value)) {
            return \false;
        }
        if (!array_is_list($value)) {
            return \false;
        }
        if ($this === self::native()) {
            return \true;
        }
        return Polyfill::array_all($value, fn(mixed $item) => $this->subType->accepts($item));
    }
    public function compiledAccept(Node $node): Node
    {
        $condition = logicalAnd($node->different(value([])), call('is_array', [$node]), call('array_is_list', [$node]));
        if ($this === self::native()) {
            return $condition;
        }
        return $condition->and(call(Polyfill::array_all_name(), [$node, shortClosure(return: $this->subType->compiledAccept(variable('item'))->wrap(), parameters: [param('item', 'mixed')])]));
    }
    public function matches(Type $other): bool
    {
        if ($other instanceof MixedType) {
            return \true;
        }
        if ($other instanceof UnionType) {
            return $other->isMatchedBy($this);
        }
        if ($other instanceof ArrayType || $other instanceof NonEmptyArrayType || $other instanceof IterableType) {
            return $other->keyType() !== ArrayKeyType::string() && $this->subType->matches($other->subType());
        }
        if ($other instanceof self || $other instanceof ListType) {
            return $this->subType->matches($other->subType());
        }
        return \false;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        if (!$other instanceof CompositeTraversableType) {
            return $generics;
        }
        return $this->subType->inferGenericsFrom($other->subType(), $generics);
    }
    public function keyType(): ArrayKeyType
    {
        return ArrayKeyType::integer();
    }
    public function subType(): Type
    {
        return $this->subType;
    }
    public function traverse(): array
    {
        return [$this->subType];
    }
    public function replace(callable $callback): Type
    {
        return new self($callback($this->subType));
    }
    public function nativeType(): ArrayType
    {
        return ArrayType::native();
    }
    public function dumpParts(): iterable
    {
        yield 'non-empty-list<';
        yield $this->subType;
        yield '>';
    }
    public function toString(): string
    {
        if ($this === self::native()) {
            return 'non-empty-list';
        }
        return "non-empty-list<{$this->subType->toString()}>";
    }
}
