<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Builder;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Shell;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\EnumType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\InterfaceType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IterableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\MixedType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeClassType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonEmptyArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NonEmptyListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NullType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedListType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UndefinedObjectType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnionType;
/** @internal */
final class TypeNodeBuilder implements NodeBuilder
{
    public function __construct(private ArrayNodeBuilder $arrayNodeBuilder, private ListNodeBuilder $listNodeBuilder, private ScalarNodeBuilder $scalarNodeBuilder, private UnionNodeBuilder $unionNodeBuilder, private NullNodeBuilder $nullNodeBuilder, private MixedNodeBuilder $mixedNodeBuilder, private UndefinedObjectNodeBuilder $undefinedObjectNodeBuilder, private KeyConverterNodeBuilder $shapedArrayNodeBuilder, private ObjectNodeBuilder $objectNodeBuilder)
    {
    }
    public function build(Shell $shell): Node
    {
        $builder = match ($shell->type::class) {
            // List
            ListType::class, NonEmptyListType::class => $this->listNodeBuilder,
            // Array
            ArrayType::class, NonEmptyArrayType::class, IterableType::class => $this->arrayNodeBuilder,
            // ShapedArray / ShapedList
            ShapedArrayType::class, ShapedListType::class => $this->shapedArrayNodeBuilder,
            // Union
            UnionType::class => $this->unionNodeBuilder,
            // Null
            NullType::class => $this->nullNodeBuilder,
            // Mixed
            MixedType::class => $this->mixedNodeBuilder,
            // Undefined object
            UndefinedObjectType::class => $this->undefinedObjectNodeBuilder,
            // Object
            NativeClassType::class, EnumType::class, InterfaceType::class => $this->objectNodeBuilder,
            // Scalar
            default => $this->scalarNodeBuilder,
        };
        return $builder->build($shell);
    }
}
