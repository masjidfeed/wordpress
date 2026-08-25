<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\MappingLogicalException;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use LogicException;
/** @internal */
final class UnresolvableShellType extends LogicException implements MappingLogicalException
{
    public function __construct(UnresolvableType $type)
    {
        parent::__construct($type->message());
    }
}
