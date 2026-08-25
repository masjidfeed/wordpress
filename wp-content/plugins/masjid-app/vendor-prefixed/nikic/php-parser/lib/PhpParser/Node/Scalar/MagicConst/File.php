<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;

use Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;
class File extends MagicConst
{
    public function getName(): string
    {
        return '__FILE__';
    }
    public function getType(): string
    {
        return 'Scalar_MagicConst_File';
    }
}
