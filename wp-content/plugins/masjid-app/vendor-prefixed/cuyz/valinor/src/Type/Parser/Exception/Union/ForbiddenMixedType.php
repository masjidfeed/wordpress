<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Union;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use LogicException;
/** @internal */
final class ForbiddenMixedType extends LogicException implements InvalidType
{
    public function __construct()
    {
        parent::__construct("Type `mixed` can only be used as a standalone type and not as a union member.");
    }
}
