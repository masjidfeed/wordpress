<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Attributes;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\MethodDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Methods;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Properties;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\PropertyDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\AttributesRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\ClassDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ClassGenericResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ClassImportedTypeAliasResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ClassLocalTypeAliasResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ClassParentTypeResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ReflectionTypeResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Object\Constructor;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectWithGenericType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Factory\TypeParserFactory;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\InterfaceType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeClassType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Reflection;
use ReflectionMethod;
use ReflectionProperty;
use function array_count_values;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function assert;
/** @internal */
final class ReflectionClassDefinitionRepository implements ClassDefinitionRepository
{
    private TypeParserFactory $typeParserFactory;
    private AttributesRepository $attributesRepository;
    private ReflectionPropertyDefinitionBuilder $propertyBuilder;
    private ReflectionMethodDefinitionBuilder $methodBuilder;
    private ClassParentTypeResolver $parentTypeResolver;
    private ClassGenericResolver $genericResolver;
    private ClassLocalTypeAliasResolver $localTypeAliasResolver;
    private ClassImportedTypeAliasResolver $importedTypeAliasResolver;
    /**
     * @param list<class-string> $allowedAttributes
     */
    public function __construct(TypeParserFactory $typeParserFactory, array $allowedAttributes)
    {
        $this->typeParserFactory = $typeParserFactory;
        $this->attributesRepository = new ReflectionAttributesRepository($this, $allowedAttributes);
        $this->propertyBuilder = new ReflectionPropertyDefinitionBuilder($this->attributesRepository);
        $this->methodBuilder = new ReflectionMethodDefinitionBuilder($this->attributesRepository, $this->typeParserFactory);
        $this->parentTypeResolver = new ClassParentTypeResolver($this->typeParserFactory);
        $this->genericResolver = new ClassGenericResolver($this->typeParserFactory);
        $this->localTypeAliasResolver = new ClassLocalTypeAliasResolver($this->typeParserFactory);
        $this->importedTypeAliasResolver = new ClassImportedTypeAliasResolver($this->typeParserFactory);
    }
    public function for(ObjectType $type): ClassDefinition
    {
        $reflection = Reflection::class($type->className());
        $generics = [];
        if ($type instanceof ObjectWithGenericType) {
            $generics = $this->genericResolver->resolveGenerics($type);
            $type = new $type($type->className(), array_values($generics));
        }
        $vacantTypes = $this->vacantTypes($type, $generics);
        $nativeTypeParser = $this->typeParserFactory->buildNativeTypeParserForClass($type->className());
        $advancedTypeParser = $this->typeParserFactory->buildAdvancedTypeParserForClass($type->className());
        $typeResolver = new ReflectionTypeResolver($nativeTypeParser, $advancedTypeParser, $vacantTypes);
        return new ClassDefinition($reflection->name, $type, new Attributes(...$this->attributesRepository->for($reflection)), new Properties(...$this->properties($type, $typeResolver)), new Methods(...$this->methods($type, $typeResolver)), $reflection->isFinal(), $reflection->isAbstract());
    }
    /**
     * @param array<non-empty-string, Type> $generics
     * @return array<non-empty-string, Type>
     */
    private function vacantTypes(ObjectType $type, array $generics): array
    {
        $localTypes = $this->localTypeAliasResolver->resolveLocalTypeAliases($type);
        $importedTypes = $this->importedTypeAliasResolver->resolveImportedTypeAliases($type);
        $vacantTypes = [...$generics, ...$localTypes, ...$importedTypes];
        $keys = [...array_keys($generics), ...array_keys($localTypes), ...array_keys($importedTypes)];
        // PHP8.5 use pipes
        $aliasCollision = array_filter(array_count_values($keys), fn(int $count) => $count > 1);
        foreach ($aliasCollision as $alias => $numberOfCollisions) {
            /** @var non-empty-string $alias */
            $vacantTypes[$alias] = UnresolvableType::forClassTypeAliasesCollision($alias, $numberOfCollisions);
        }
        return $vacantTypes;
    }
    /**
     * @return list<PropertyDefinition>
     */
    private function properties(ObjectType $type, ReflectionTypeResolver $typeResolver): array
    {
        $reflection = Reflection::class($type->className());
        $properties = [];
        $parentClasses = [];
        foreach ($reflection->getProperties() as $property) {
            $declaringClass = $property->getDeclaringClass();
            if ($declaringClass->name === $type->className()) {
                $properties[$property->name] = $this->propertyBuilder->for($property, $typeResolver);
            } else {
                assert($type instanceof NativeClassType || $type instanceof InterfaceType);
                $parentClass = $this->parentTypeResolver->resolveParentTypeFor($type, $property);
                // @infection-ignore-all Just some memoization
                $parentClasses[$parentClass->toString()] ??= $this->for($parentClass);
                $properties[$property->name] = $parentClasses[$parentClass->toString()]->properties->get($property->name);
            }
        }
        // Properties will be sorted by inheritance order, from parent to child.
        $sortedProperties = [];
        while ($reflection) {
            $currentProperties = array_map(fn(ReflectionProperty $property) => $properties[$property->name], array_filter($reflection->getProperties(), fn(ReflectionProperty $property) => isset($properties[$property->name])));
            $sortedProperties = [...$currentProperties, ...$sortedProperties];
            $reflection = $reflection->getParentClass();
        }
        return $sortedProperties;
    }
    /**
     * @return array<MethodDefinition>
     */
    private function methods(ObjectType $type, ReflectionTypeResolver $typeResolver): array
    {
        $reflection = Reflection::class($type->className());
        $methods = array_filter($reflection->getMethods(), $this->shouldMethodBeIncluded(...));
        $parentClasses = [];
        $definitions = [];
        // Because `ReflectionMethod::getMethods()` wont list the constructor if
        // it comes from a parent class AND is not public, we need to manually
        // fetch it and add it to the list.
        if ($reflection->hasMethod('__construct')) {
            $methods[] = $reflection->getMethod('__construct');
        }
        foreach ($methods as $method) {
            $declaringClass = $method->getDeclaringClass();
            if ($declaringClass->name === $type->className()) {
                $definitions[] = $this->methodBuilder->for($method, $typeResolver);
                continue;
            }
            assert($type instanceof NativeClassType || $type instanceof InterfaceType);
            $parentClass = $this->parentTypeResolver->resolveParentTypeFor($type, $method);
            // @infection-ignore-all Just some memoization
            $parentClasses[$parentClass->toString()] ??= $this->for($parentClass);
            $definitions[] = $parentClasses[$parentClass->toString()]->methods->get($method->name);
        }
        return $definitions;
    }
    private function shouldMethodBeIncluded(ReflectionMethod $method): bool
    {
        return $method->name === 'map' || $method->name === 'mapKey' || $method->name === 'normalize' || $method->name === 'normalizeKey' || $method->getAttributes(Constructor::class) !== [];
    }
}
