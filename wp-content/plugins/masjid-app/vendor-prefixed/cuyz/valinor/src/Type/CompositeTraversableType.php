<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ArrayKeyType;
/** @internal */
interface CompositeTraversableType extends CompositeType
{
    public function keyType(): ArrayKeyType;
    public function subType(): Type;
}
