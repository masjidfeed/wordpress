<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
/** @internal */
final class DateTimeFormatter implements TypeFormatter
{
    public function formatValueNode(Node $valueNode): Node
    {
        return $valueNode->callMethod('format', [value('Y-m-d\TH:i:s.uP')]);
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        return $class;
    }
}
