<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Source\Exception;

use Throwable;
/** @api */
interface InvalidSource extends Throwable
{
    public function source(): mixed;
}
