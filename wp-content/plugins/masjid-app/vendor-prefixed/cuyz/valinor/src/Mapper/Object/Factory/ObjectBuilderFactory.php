<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Factory;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\ObjectBuilder;
/** @internal */
interface ObjectBuilderFactory
{
    /**
     * @return non-empty-list<ObjectBuilder>
     */
    public function for(ClassDefinition $class): array;
}
