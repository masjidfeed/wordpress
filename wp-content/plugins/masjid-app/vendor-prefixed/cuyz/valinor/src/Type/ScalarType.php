<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\ErrorMessage;
/** @internal */
interface ScalarType extends Type
{
    public function errorMessage(): ErrorMessage;
}
