<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Stmt;

use Masjid_App\Dependencies\PhpParser\Node;
/** Nop/empty statement (;). */
class Nop extends Node\Stmt
{
    public function getSubNodeNames(): array
    {
        return [];
    }
    public function getType(): string
    {
        return 'Stmt_Nop';
    }
}
