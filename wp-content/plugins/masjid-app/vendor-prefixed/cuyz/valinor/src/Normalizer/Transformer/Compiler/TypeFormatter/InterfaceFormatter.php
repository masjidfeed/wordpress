<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TypeFormatter;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Native\AnonymousClassNode;
use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Normalizer\Transformer\Compiler\TransformerDefinitionBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\InterfaceType;
use DateTimeInterface;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\this;
/** @internal */
final class InterfaceFormatter implements TypeFormatter
{
    public function __construct(private InterfaceType $type)
    {
    }
    public function formatValueNode(Node $valueNode): Node
    {
        if ($this->type->className() === DateTimeInterface::class) {
            return (new DateTimeFormatter())->formatValueNode($valueNode);
        }
        return this()->access('delegate')->callMethod('transform', [$valueNode]);
    }
    public function manipulateTransformerClass(AnonymousClassNode $class, TransformerDefinitionBuilder $definitionBuilder): AnonymousClassNode
    {
        return $class;
    }
}
