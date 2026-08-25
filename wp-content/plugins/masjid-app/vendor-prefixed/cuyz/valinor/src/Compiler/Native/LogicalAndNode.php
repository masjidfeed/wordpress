<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use function array_shift;
/** @internal */
final class LogicalAndNode extends Node
{
    /** @var list<Node> */
    private array $nodes;
    /**
     * @no-named-arguments
     */
    public function __construct(Node ...$nodes)
    {
        $this->nodes = $nodes;
    }
    public function compile(Compiler $compiler): Compiler
    {
        $nodes = $this->nodes;
        while ($node = array_shift($nodes)) {
            $compiler = $compiler->compile($node);
            if ($nodes !== []) {
                $compiler = $compiler->write(' && ');
            }
        }
        return $compiler;
    }
}
