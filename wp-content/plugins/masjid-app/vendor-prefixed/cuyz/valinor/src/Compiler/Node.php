<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Compiler;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\ArrayKeyAccessNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AssignNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\CallNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\ClassConstantNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\DifferentNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\EqualsNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\GreaterOrEqualsToNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\GreaterThanNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\InstanceOfNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\LessOrEqualsToNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\LessThanNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\LogicalAndNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\LogicalOrNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\MethodCallNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\StatementNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\StaticMethodCallNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\VariableAccessNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\WrapNode;
/** @internal */
abstract class Node
{
    abstract public function compile(Compiler $compiler): Compiler;
    /**
     * @param non-empty-string $value
     */
    public function access(string $value): self
    {
        return new VariableAccessNode($this, $value);
    }
    /**
     * @no-named-arguments
     */
    public function and(self ...$nodes): self
    {
        return new LogicalAndNode($this, ...$nodes);
    }
    public function asClassConstant(): self
    {
        return new ClassConstantNode($this);
    }
    public function asStatement(): self
    {
        return new StatementNode($this);
    }
    public function key(self $key): self
    {
        return new ArrayKeyAccessNode($this, $key);
    }
    public function assign(self $value): self
    {
        return new AssignNode($this, $value);
    }
    /**
     * @param array<Node> $arguments
     */
    public function call(array $arguments = []): self
    {
        return new CallNode($this, $arguments);
    }
    /**
     * @param non-empty-string $method
     * @param array<Node> $arguments
     */
    public function callMethod(string $method, array $arguments = []): self
    {
        return new MethodCallNode($this, $method, $arguments);
    }
    /**
     * @param non-empty-string $method
     * @param array<Node> $arguments
     */
    public function callStaticMethod(string $method, array $arguments = []): self
    {
        return new StaticMethodCallNode($this, $method, $arguments);
    }
    public function different(self $right): self
    {
        return new DifferentNode($this, $right);
    }
    public function equals(self $right): self
    {
        return new EqualsNode($this, $right);
    }
    /**
     * @no-named-arguments
     */
    public function or(self ...$nodes): self
    {
        return new LogicalOrNode($this, ...$nodes);
    }
    /**
     * @param class-string $className
     */
    public function instanceOf(string $className): self
    {
        return new InstanceOfNode($this, $className);
    }
    public function isLessThan(self $right): self
    {
        return new LessThanNode($this, $right);
    }
    public function isLessOrEqualsTo(self $right): self
    {
        return new LessOrEqualsToNode($this, $right);
    }
    public function isGreaterThan(self $right): self
    {
        return new GreaterThanNode($this, $right);
    }
    public function isGreaterOrEqualsTo(self $right): self
    {
        return new GreaterOrEqualsToNode($this, $right);
    }
    public function wrap(): self
    {
        return new WrapNode($this);
    }
}
