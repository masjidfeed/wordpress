<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use LogicException;
/** @internal */
final class KeyConverterHasTooManyParameters extends LogicException
{
    public function __construct(MethodDefinition|FunctionDefinition $method)
    {
        parent::__construct("Key converter must have only one parameter, {$method->parameters->count()} given for `{$method->signature}`.");
    }
}
