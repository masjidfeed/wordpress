<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Attributes;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Parameters;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\AttributesRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\FunctionDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\FunctionReturnTypeResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\ReflectionTypeResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver\TemplateResolver;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Factory\TypeParserFactory;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Reflection;
use ReflectionFunction;
use ReflectionParameter;
use function array_map;
/** @internal */
final class ReflectionFunctionDefinitionRepository implements FunctionDefinitionRepository
{
    private TypeParserFactory $typeParserFactory;
    private AttributesRepository $attributesRepository;
    private ReflectionParameterDefinitionBuilder $parameterBuilder;
    private TemplateResolver $templateResolver;
    public function __construct(TypeParserFactory $typeParserFactory, AttributesRepository $attributesRepository)
    {
        $this->typeParserFactory = $typeParserFactory;
        $this->attributesRepository = $attributesRepository;
        $this->parameterBuilder = new ReflectionParameterDefinitionBuilder($attributesRepository);
        $this->templateResolver = new TemplateResolver();
    }
    public function for(callable $function): FunctionDefinition
    {
        $reflection = Reflection::function($function);
        $signature = $this->signature($reflection);
        $nativeParser = $this->typeParserFactory->buildNativeTypeParserForFunction($function);
        $advancedParser = $this->typeParserFactory->buildAdvancedTypeParserForFunction($function);
        $templates = $this->templateResolver->templatesFromDocBlock($reflection, $signature, $advancedParser);
        $typeResolver = new ReflectionTypeResolver($nativeParser, $advancedParser, $templates);
        $returnTypeResolver = new FunctionReturnTypeResolver($typeResolver);
        $parameters = array_map(fn(ReflectionParameter $parameter) => $this->parameterBuilder->for($parameter, $typeResolver), $reflection->getParameters());
        $name = $reflection->getName();
        $class = $reflection->getClosureScopeClass();
        $returnType = $returnTypeResolver->resolveReturnTypeFor($reflection);
        $nativeReturnType = $returnTypeResolver->resolveNativeReturnTypeFor($reflection);
        if ($returnType instanceof UnresolvableType) {
            $returnType = $returnType->forFunctionReturnType($signature);
        } elseif (!$returnType->matches($nativeReturnType)) {
            $returnType = UnresolvableType::forNonMatchingTypes($nativeReturnType, $returnType)->forFunctionReturnType($signature);
        }
        return (new FunctionDefinition($name, $signature, new Attributes(...$this->attributesRepository->for($reflection)), $reflection->getFileName() ?: null, $class?->name, $reflection->getClosureThis() === null, $reflection->isAnonymous(), new Parameters(...$parameters), $returnType))->forCallable($function);
    }
    /**
     * @return non-empty-string
     */
    private function signature(ReflectionFunction $reflection): string
    {
        if ($reflection->isAnonymous()) {
            $startLine = $reflection->getStartLine();
            $endLine = $reflection->getEndLine();
            return $startLine === $endLine ? "Closure (line {$startLine} of {$reflection->getFileName()})" : "Closure (lines {$startLine} to {$endLine} of {$reflection->getFileName()})";
        }
        return $reflection->getClosureScopeClass() ? $reflection->getClosureScopeClass()->name . '::' . $reflection->name . '()' : $reflection->name . '()';
    }
}
