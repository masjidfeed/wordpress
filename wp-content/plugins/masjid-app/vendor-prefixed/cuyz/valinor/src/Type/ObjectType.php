<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

/** @internal */
interface ObjectType extends Type
{
    /**
     * @return class-string
     */
    public function className(): string;
    public function nativeType(): ObjectType;
}
