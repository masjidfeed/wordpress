<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Reflection\TypeResolver;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectWithGenericType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Factory\TypeParserFactory;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Reflection;
use function array_shift;
/** @internal */
final class ClassGenericResolver
{
    private TemplateResolver $templateResolver;
    public function __construct(private TypeParserFactory $typeParserFactory)
    {
        $this->templateResolver = new TemplateResolver();
    }
    /**
     * @return array<non-empty-string, Type>
     */
    public function resolveGenerics(ObjectWithGenericType $type): array
    {
        $typeParser = $this->typeParserFactory->buildAdvancedTypeParserForClass($type->className());
        $templates = $this->templateResolver->templatesFromDocBlock(Reflection::class($type->className()), $type->className(), $typeParser);
        $generics = $type->generics();
        $assignedGenerics = [];
        foreach ($templates as $template => $templateType) {
            if ($generics !== []) {
                $generic = array_shift($generics);
            } elseif ($templateType->default !== null) {
                $generic = $templateType->default;
            } else {
                $generic = $templateType;
            }
            if ($templateType->innerType instanceof UnresolvableType) {
                $generic = $templateType->innerType;
            } elseif (!$generic instanceof UnresolvableType && !$generic->matches($templateType->innerType)) {
                $generic = UnresolvableType::forInvalidAssignedGeneric($generic, $templateType->innerType, $template, $type->className());
            }
            $assignedGenerics[$template] = $generic;
        }
        return $assignedGenerics;
    }
}
