<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Attributes;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
final class ShapedArrayElement
{
    public function __construct(private StringValueType|IntegerValueType $key, private Type $type, private bool $optional = \false, private ?Attributes $attributes = null)
    {
    }
    public function key(): StringValueType|IntegerValueType
    {
        return $this->key;
    }
    public function type(): Type
    {
        return $this->type;
    }
    public function isOptional(): bool
    {
        return $this->optional;
    }
    public function attributes(): Attributes
    {
        return $this->attributes ?? Attributes::empty();
    }
    public function toString(): string
    {
        return $this->isOptional() ? "{$this->key->toString()}?: {$this->type->toString()}" : "{$this->key->toString()}: {$this->type->toString()}";
    }
}
