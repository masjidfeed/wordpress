<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Library;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
final class TypeAcceptNode extends Node
{
    public function __construct(private Node $node, private Type $type)
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        return $compiler->compile($this->type->compiledAccept($this->node));
    }
}
