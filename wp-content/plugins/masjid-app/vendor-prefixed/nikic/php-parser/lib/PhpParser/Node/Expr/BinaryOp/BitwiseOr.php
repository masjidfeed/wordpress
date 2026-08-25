<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Expr\BinaryOp;

use Masjid_App\Dependencies\PhpParser\Node\Expr\BinaryOp;
class BitwiseOr extends BinaryOp
{
    public function getOperatorSigil(): string
    {
        return '|';
    }
    public function getType(): string
    {
        return 'Expr_BinaryOp_BitwiseOr';
    }
}
