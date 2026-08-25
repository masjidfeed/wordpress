<?php

namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

/** @internal */
interface FixedType extends Type
{
    public function value(): bool|string|int|float;
}
