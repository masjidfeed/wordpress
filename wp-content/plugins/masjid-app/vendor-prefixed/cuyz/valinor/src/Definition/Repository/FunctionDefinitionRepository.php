<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
/** @internal */
interface FunctionDefinitionRepository
{
    public function for(callable $function): FunctionDefinition;
}
