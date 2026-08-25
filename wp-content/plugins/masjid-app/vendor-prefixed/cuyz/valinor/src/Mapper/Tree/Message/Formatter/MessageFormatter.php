<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\Formatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Message\NodeMessage;
/** @api */
interface MessageFormatter
{
    /** @pure */
    public function format(NodeMessage $message): NodeMessage;
}
