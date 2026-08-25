<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Factory;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Argument;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Exception\PermissiveTypeNotAllowed;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UndefinedObjectType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\TypeHelper;
/** @internal */
final class StrictTypesObjectBuilderFactory implements ObjectBuilderFactory
{
    public function __construct(private ObjectBuilderFactory $delegate)
    {
    }
    public function for(ClassDefinition $class): array
    {
        $builders = $this->delegate->for($class);
        foreach ($builders as $builder) {
            $arguments = $builder->describeArguments();
            foreach ($arguments as $argument) {
                $this->checkPresenceOfPermissiveType($argument, $argument->type());
            }
        }
        return $builders;
    }
    private function checkPresenceOfPermissiveType(Argument $argument, Type $type): void
    {
        foreach (TypeHelper::traverseRecursively($type) as $subType) {
            self::checkPresenceOfPermissiveType($argument, $subType);
        }
        if ($type instanceof MixedType || $type instanceof UndefinedObjectType) {
            throw new PermissiveTypeNotAllowed($argument->signature(), $type);
        }
    }
}
