<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectType;
/** @internal */
interface ClassDefinitionRepository
{
    public function for(ObjectType $type): ClassDefinition;
}
