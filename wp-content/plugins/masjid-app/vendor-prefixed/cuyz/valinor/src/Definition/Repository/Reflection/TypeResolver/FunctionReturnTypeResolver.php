<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Annotations;
use ReflectionFunctionAbstract;
/** @internal */
final class FunctionReturnTypeResolver
{
    public function __construct(private ReflectionTypeResolver $typeResolver)
    {
    }
    public function resolveReturnTypeFor(ReflectionFunctionAbstract $reflection): Type
    {
        $docBlockType = Annotations::forFunctionReturnType($reflection);
        return $this->typeResolver->resolveType($reflection->getReturnType(), $docBlockType);
    }
    public function resolveNativeReturnTypeFor(ReflectionFunctionAbstract $reflection): Type
    {
        return $this->typeResolver->resolveNativeType($reflection->getReturnType());
    }
}
