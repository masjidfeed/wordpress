<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayType;
use WeakMap;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\forEach_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\match_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\param;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\return_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\this;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\variable;
use function hash;
/** @internal */
final class ShapedArrayFormatter implements TypeFormatter
{
    public function __construct(private ShapedArrayType $type)
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
        if ($this->type->isUnsealed() && $this->type->unsealedType() instanceof ArrayType) {
            $defaultDefinition = $definitionBuilder->for($this->type->unsealedType()->subType());
        } else {
            $defaultDefinition = $definitionBuilder->for(MixedType::get());
        }
        $class = $defaultDefinition->typeFormatter()->manipulateTransformerClass($class, $definitionBuilder);
        $elementsDefinitions = [];
        foreach ($this->type->elements as $key => $element) {
            $elementDefinition = $definitionBuilder->for($element->type());
            $class = $elementDefinition->typeFormatter()->manipulateTransformerClass($class, $definitionBuilder);
            $elementsDefinitions[$key] = $elementDefinition;
        }
        return $class->withMethod(name: $methodName, parameters: [param('value', 'array'), param('references', WeakMap::class)], returnType: 'array', body: [variable('result')->assign(value([]))->asStatement(), forEach_(value: variable('value'), key: 'key', item: 'item', body: variable('result')->key(variable('key'))->assign((function () use ($defaultDefinition, $elementsDefinitions) {
            $match = match_(variable('key'));
            foreach ($elementsDefinitions as $name => $definition) {
                $match = $match->withCase(condition: value($name), body: $definition->typeFormatter()->formatValueNode(variable('item')));
            }
            return $match->withDefaultCase($defaultDefinition->typeFormatter()->formatValueNode(variable('item')));
        })())->asStatement()), return_(variable('result'))]);
    }
    /**
     * @return non-empty-string
     */
    private function methodName(): string
    {
        return 'transform_shaped_array_' . hash('crc32', $this->type->toString());
    }
}
