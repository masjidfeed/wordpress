<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
/** @internal */
final class ArrayKeyAccessNode extends Node
{
    public function __construct(private Node $node, private Node $key)
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        $key = $compiler->sub()->compile($this->key)->code();
        return $compiler->compile($this->node)->write('[' . $key . ']');
    }
}
