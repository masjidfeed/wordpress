<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use LogicException;
/** @internal */
final class TransformerHasNoParameter extends LogicException
{
    public function __construct(MethodDefinition|FunctionDefinition $method)
    {
        parent::__construct("Transformer must have at least one parameter, none given for `{$method->signature}`.");
    }
}
