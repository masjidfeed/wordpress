<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Stmt;

use Masjid_App\Dependencies\PhpParser\Node;
abstract class TraitUseAdaptation extends Node\Stmt
{
    /** @var Node\Name|null Trait name */
    public ?Node\Name $trait;
    /** @var Node\Identifier Method name */
    public Node\Identifier $method;
}
