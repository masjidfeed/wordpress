<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Factory;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\ReflectionObjectBuilder;
/** @internal */
final class ReflectionObjectBuilderFactory implements ObjectBuilderFactory
{
    public function for(ClassDefinition $class): array
    {
        return [new ReflectionObjectBuilder($class)];
    }
}
