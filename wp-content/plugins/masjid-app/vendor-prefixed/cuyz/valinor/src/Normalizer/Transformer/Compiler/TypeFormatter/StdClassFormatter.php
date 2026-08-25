<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\EmptyObject;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use stdClass;
use WeakMap;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\call;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\castToArray;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\className;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\if_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\param;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\return_;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\shortClosure;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\this;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\variable;
/** @internal */
final class StdClassFormatter implements TypeFormatter
{
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        if ($class->hasMethod('transform_stdclass')) {
            return $class;
        }
        $defaultDefinition = $definitionBuilder->for(MixedType::get());
        $class = $defaultDefinition->typeFormatter()->manipulateTransformerClass($class, $definitionBuilder);
        return $class->withMethod(name: 'transform_stdclass', parameters: [param('value', stdClass::class), param('references', WeakMap::class)], returnType: 'mixed', body: [variable('values')->assign(castToArray(variable('value')))->asStatement(), if_(condition: variable('values')->equals(value([])), body: return_(className(EmptyObject::class)->callStaticMethod('get'))), return_(call(name: 'array_map', arguments: [shortClosure(return: $defaultDefinition->typeFormatter()->formatValueNode(variable('value')), parameters: [param('value', 'mixed')]), castToArray(variable('value'))]))]);
    }
    public function formatValueNode(Node $valueNode): Node
    {
        return this()->callMethod(method: 'transform_stdclass', arguments: [$valueNode, variable('references')]);
    }
}
