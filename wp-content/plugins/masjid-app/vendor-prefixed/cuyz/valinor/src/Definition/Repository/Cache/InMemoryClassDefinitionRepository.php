<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Cache;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\ClassDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectType;
/** @internal */
final class InMemoryClassDefinitionRepository implements ClassDefinitionRepository
{
    /** @var array<string, ClassDefinition> */
    private array $classDefinitions = [];
    public function __construct(private ClassDefinitionRepository $delegate)
    {
    }
    public function for(ObjectType $type): ClassDefinition
    {
        return $this->classDefinitions[$type->toString()] ??= $this->delegate->for($type);
    }
}
