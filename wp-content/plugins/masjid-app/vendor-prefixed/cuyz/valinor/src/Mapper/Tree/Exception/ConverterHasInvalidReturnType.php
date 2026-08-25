<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use RuntimeException;
use function assert;
/** @internal */
final class ConverterHasInvalidReturnType extends RuntimeException
{
    public function __construct(FunctionDefinition $function)
    {
        assert($function->returnType instanceof UnresolvableType);
        parent::__construct($function->returnType->message());
    }
}
