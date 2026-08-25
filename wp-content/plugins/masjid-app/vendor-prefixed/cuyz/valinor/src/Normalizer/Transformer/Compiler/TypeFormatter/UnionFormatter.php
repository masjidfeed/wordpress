<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Library\TypeAcceptNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnionType;
use WeakMap;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\if_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\param;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\return_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\this;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\variable;
use function hash;
/** @internal */
final class UnionFormatter implements TypeFormatter
{
    public function __construct(private UnionType $type)
    {
    }
    public function formatValueNode(Node $valueNode): Node
    {
        return this()->callMethod(method: $this->methodName(), arguments: [$valueNode, variable('references')]);
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        $methodName = $this->methodName();
        if ($class->hasMethod($methodName)) {
            return $class;
        }
        $defaultDefinition = $definitionBuilder->for(MixedType::get());
        $class = $defaultDefinition->typeFormatter()->manipulateTransformerClass($class, $definitionBuilder);
        $nodes = [];
        foreach ($this->type->types() as $subType) {
            $definition = $definitionBuilder->for($subType)->markAsSure();
            $class = $definition->typeFormatter()->manipulateTransformerClass($class, $definitionBuilder);
            $nodes[] = if_(condition: new TypeAcceptNode(variable('value'), $definition->type), body: return_($definition->typeFormatter()->formatValueNode(variable('value'))));
        }
        $nodes[] = return_($defaultDefinition->typeFormatter()->formatValueNode(variable('value')));
        return $class->withMethod(name: $methodName, parameters: [param('value', 'mixed'), param('references', WeakMap::class)], returnType: 'mixed', body: $nodes);
    }
    /**
     * @return non-empty-string
     */
    private function methodName(): string
    {
        return 'transform_union_' . hash('crc32', $this->type->toString());
    }
}
