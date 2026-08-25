<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use function in_array;
/** @internal */
final class FunctionNameNode extends Node
{
    private const RESERVED_FUNCTIONS = ['isset'];
    public function __construct(
        /** @var non-empty-string */
        private string $name
    )
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        $function = in_array($this->name, self::RESERVED_FUNCTIONS, \true) ? $this->name : '\\' . $this->name;
        return $compiler->write($function);
    }
}
