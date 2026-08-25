<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

/** @internal */
interface DumpableType extends Type
{
    /**
     * Returns parts of the type which will be used to recursively dump it.
     *
     * @return iterable<non-empty-string|Type>
     */
    public function dumpParts(): iterable;
}
