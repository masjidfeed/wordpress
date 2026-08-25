<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
/** @internal */
final class DateTimeZoneFormatter implements TypeFormatter
{
    public function formatValueNode(Node $valueNode): Node
    {
        return $valueNode->callMethod('getName');
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        return $class;
    }
}
