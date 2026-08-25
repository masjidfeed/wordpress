<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Expr\AssignOp;

use Masjid_App\Dependencies\PhpParser\Node\Expr\AssignOp;
class Concat extends AssignOp
{
    public function getType(): string
    {
        return 'Expr_AssignOp_Concat';
    }
}
