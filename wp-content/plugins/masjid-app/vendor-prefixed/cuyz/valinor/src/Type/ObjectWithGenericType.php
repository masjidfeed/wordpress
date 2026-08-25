<?php

namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

/** @internal */
interface ObjectWithGenericType extends ObjectType, CompositeType
{
    /**
     * @return class-string
     */
    public function className(): string;
    /**
     * @return list<Type>
     */
    public function generics(): array;
}
