<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use RuntimeException;
/** @internal */
final class KeyConverterHasNoParameter extends RuntimeException
{
    public function __construct(MethodDefinition|FunctionDefinition $function)
    {
        parent::__construct("The key converter `{$function->signature}` has no parameter to convert the key, a string parameter is required.");
    }
}
