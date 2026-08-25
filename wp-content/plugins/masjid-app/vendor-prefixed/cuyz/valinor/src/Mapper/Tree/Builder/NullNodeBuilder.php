<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Builder;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception\SourceIsNotNull;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Shell;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NullType;
use function assert;
/** @internal */
final class NullNodeBuilder implements NodeBuilder
{
    public function build(Shell $shell): Node
    {
        $value = $shell->value();
        assert($shell->type instanceof NullType);
        if ($value !== null) {
            return $shell->error(new SourceIsNotNull());
        }
        return $shell->node(null);
    }
}
