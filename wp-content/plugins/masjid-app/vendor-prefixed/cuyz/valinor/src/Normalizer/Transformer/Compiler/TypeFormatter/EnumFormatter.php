<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use BackedEnum;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\EnumType;
use function is_a;
/** @internal */
final class EnumFormatter implements TypeFormatter
{
    public function __construct(private EnumType $type)
    {
    }
    public function formatValueNode(Node $valueNode): Node
    {
        return is_a($this->type->className(), BackedEnum::class, \true) ? $valueNode->access('value') : $valueNode->access('name');
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        return $class;
    }
}
