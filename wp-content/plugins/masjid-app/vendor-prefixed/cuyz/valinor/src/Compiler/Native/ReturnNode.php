<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
/** @internal */
final class ReturnNode extends Node
{
    public function __construct(private ?Node $node = null)
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        $code = $this->node ? ' ' . $compiler->sub()->compile($this->node)->code() : '';
        return $compiler->write("return{$code};");
    }
}
