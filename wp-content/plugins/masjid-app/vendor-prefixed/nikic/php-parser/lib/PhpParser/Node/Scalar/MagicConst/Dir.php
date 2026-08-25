<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;

use Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;
class Dir extends MagicConst
{
    public function getName(): string
    {
        return '__DIR__';
    }
    public function getType(): string
    {
        return 'Scalar_MagicConst_Dir';
    }
}
