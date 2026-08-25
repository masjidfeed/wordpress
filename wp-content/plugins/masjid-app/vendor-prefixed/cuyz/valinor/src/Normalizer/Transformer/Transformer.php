<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer;

/** @internal */
interface Transformer
{
    public function transform(mixed $value): mixed;
}
