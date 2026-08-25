<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
interface TypeParser
{
    public function parse(string $raw): Type;
}
