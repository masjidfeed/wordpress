<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
/** @internal */
final class MethodCallNode extends Node
{
    public function __construct(
        private Node $node,
        /** @var non-empty-string */
        private string $method,
        /** @var array<Node> */
        private array $arguments = []
    )
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        return $compiler->compile(new CallNode(new VariableAccessNode($this->node, $this->method), $this->arguments));
    }
}
