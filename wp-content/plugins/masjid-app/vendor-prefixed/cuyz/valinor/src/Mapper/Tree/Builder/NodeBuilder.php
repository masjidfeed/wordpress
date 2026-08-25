<?php

namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Builder;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Shell;
/** @internal */
interface NodeBuilder
{
    public function build(Shell $shell): Node;
}
