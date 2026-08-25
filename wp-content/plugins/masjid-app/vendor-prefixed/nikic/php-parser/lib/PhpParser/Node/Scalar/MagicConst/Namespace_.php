<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;

use Masjid_App\Dependencies\PhpParser\Node\Scalar\MagicConst;
class Namespace_ extends MagicConst
{
    public function getName(): string
    {
        return '__NAMESPACE__';
    }
    public function getType(): string
    {
        return 'Scalar_MagicConst_Namespace';
    }
}
