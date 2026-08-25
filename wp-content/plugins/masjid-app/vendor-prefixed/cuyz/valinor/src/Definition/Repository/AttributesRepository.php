<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\AttributeDefinition;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use Reflector;
/** @internal */
interface AttributesRepository
{
    /**
     * @param ReflectionClass<covariant object>|ReflectionProperty|ReflectionMethod|ReflectionFunction|ReflectionParameter $reflection
     * @return list<AttributeDefinition>
     */
    public function for(Reflector $reflection): array;
}
