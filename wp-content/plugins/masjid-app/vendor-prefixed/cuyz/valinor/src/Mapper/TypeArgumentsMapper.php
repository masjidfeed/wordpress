<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ParameterDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\FunctionDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\MappingLogicalException;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\TypeErrorDuringArgumentsMapping;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\RootNodeBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayElement;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\StringValueType;
use function array_map;
/** @internal */
final class TypeArgumentsMapper implements ArgumentsMapper
{
    public function __construct(private FunctionDefinitionRepository $functionDefinitionRepository, private RootNodeBuilder $nodeBuilder)
    {
    }
    /** @pure */
    public function mapArguments(callable $callable, mixed $source): array
    {
        $function = $this->functionDefinitionRepository->for($callable);
        $elements = array_map(fn(ParameterDefinition $parameter) => new ShapedArrayElement(new StringValueType($parameter->name), $parameter->type, $parameter->isOptional, $parameter->attributes), $function->parameters->toArray());
        $type = new ShapedArrayType($elements);
        try {
            $node = $this->nodeBuilder->build($source, $type, $function->attributes);
        } catch (MappingLogicalException $exception) {
            throw new TypeErrorDuringArgumentsMapping($function, $exception);
        }
        if (!$node->isValid()) {
            throw new ArgumentsMapperError($source, $type->toString(), $function->signature, $node->messages());
        }
        /** @var array<string, mixed> */
        return $node->value();
    }
}
