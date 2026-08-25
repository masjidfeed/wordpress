<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use BackedEnum;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\ternary;
/** @internal */
final class UnitEnumFormatter implements TypeFormatter
{
    public function formatValueNode(Node $valueNode): Node
    {
        return ternary(condition: $valueNode->instanceOf(BackedEnum::class), ifTrue: $valueNode->access('value'), ifFalse: $valueNode->access('name'));
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        return $class;
    }
}
