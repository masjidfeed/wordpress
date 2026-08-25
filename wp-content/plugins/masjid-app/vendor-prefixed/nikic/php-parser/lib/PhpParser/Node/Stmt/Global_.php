<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Stmt;

use Masjid_App\Dependencies\PhpParser\Node;
class Global_ extends Node\Stmt
{
    /** @var Node\Expr[] Variables */
    public array $vars;
    /**
     * Constructs a global variables list node.
     *
     * @param Node\Expr[] $vars Variables to unset
     * @param array<string, mixed> $attributes Additional attributes
     */
    public function __construct(array $vars, array $attributes = [])
    {
        $this->attributes = $attributes;
        $this->vars = $vars;
    }
    public function getSubNodeNames(): array
    {
        return ['vars'];
    }
    public function getType(): string
    {
        return 'Stmt_Global';
    }
}
