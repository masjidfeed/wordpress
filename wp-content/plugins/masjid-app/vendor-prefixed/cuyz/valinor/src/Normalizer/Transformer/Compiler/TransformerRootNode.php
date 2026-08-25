<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Compiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Transformer;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use WeakMap;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\anonymousClass;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\newClass;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\param;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\property;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\return_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\this;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\variable;
/** @internal */
final class TransformerRootNode extends Node
{
    public function __construct(private TransformerDefinitionBuilder $definitionBuilder, private Type $type)
    {
    }
    public function compile(Compiler $compiler): Compiler
    {
        $definition = $this->definitionBuilder->for($this->type);
        $definition = $definition->markAsSure();
        $classNode = $this->transformerClassNode($definition);
        $classNode = $definition->typeFormatter()->manipulateTransformerClass($classNode, $this->definitionBuilder);
        return $classNode->compile($compiler);
    }
    private function transformerClassNode(TransformerDefinition $definition): AnonymousClassNode
    {
        return anonymousClass()->implements(Transformer::class)->withArguments(variable('transformers'), variable('delegate'))->withProperties(property('transformers', 'array'), property('delegate', Transformer::class))->withConstructor(parameters: [param('transformers', 'array'), param('delegate', Transformer::class)], body: [this()->access('transformers')->assign(variable('transformers'))->asStatement(), this()->access('delegate')->assign(variable('delegate'))->asStatement()])->withMethod(name: 'transform', visibility: 'public', parameters: [param('value', 'mixed')], returnType: 'mixed', body: [variable('references')->assign(newClass(WeakMap::class))->asStatement(), return_($definition->typeFormatter()->formatValueNode(variable('value')))]);
    }
}
