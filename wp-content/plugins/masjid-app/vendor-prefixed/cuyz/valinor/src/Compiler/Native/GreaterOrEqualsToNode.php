<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
/** @internal */
final class GreaterOrEqualsToNode extends Node
{
    public function __construct(private Node $left, private Node $right)
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        return $compiler->compile($this->left)->write(' >= ')->compile($this->right);
    }
}
