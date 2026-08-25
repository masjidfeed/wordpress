<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\PhpParser\Node\Scalar;

use Masjid_App\Dependencies\PhpParser\Node\InterpolatedStringPart;
require __DIR__ . '/../InterpolatedStringPart.php';
if (\false) {
    /**
     * For classmap-authoritative support.
     *
     * @deprecated use \PhpParser\Node\InterpolatedStringPart instead.
     */
    class EncapsedStringPart extends InterpolatedStringPart
    {
    }
}
