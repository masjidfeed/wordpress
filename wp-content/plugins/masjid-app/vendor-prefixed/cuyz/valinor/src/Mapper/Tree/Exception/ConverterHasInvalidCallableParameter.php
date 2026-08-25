<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use LogicException;
/** @internal */
final class ConverterHasInvalidCallableParameter extends LogicException
{
    public function __construct(MethodDefinition|FunctionDefinition $method, Type $parameterType)
    {
        parent::__construct("Converter's second parameter must be a callable, `{$parameterType->toString()}` given for `{$method->signature}`.");
    }
}
