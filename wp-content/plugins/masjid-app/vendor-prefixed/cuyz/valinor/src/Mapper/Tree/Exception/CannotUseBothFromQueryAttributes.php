<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\MappingLogicalException;
use LogicException;
/** @internal */
final class CannotUseBothFromQueryAttributes extends LogicException implements MappingLogicalException
{
    protected $message = 'Cannot use `#[FromQuery(asRoot: true)]` alongside other `#[FromQuery]` attributes.';
}
