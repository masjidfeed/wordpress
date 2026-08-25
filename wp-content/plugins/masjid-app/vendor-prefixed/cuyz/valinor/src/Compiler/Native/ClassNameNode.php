<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
/** @internal */
final class ClassNameNode extends Node
{
    public function __construct(
        /** @var class-string */
        private string $className
    )
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        return $compiler->write($this->className);
    }
}
