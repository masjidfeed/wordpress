<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message;

/**
 * This interface can be implemented by a message to help to identify it with a
 * unique code.
 *
 * @api
 */
interface HasCode extends Message
{
    /** @pure */
    public function code(): string;
}
