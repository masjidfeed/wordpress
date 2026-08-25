<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\InvalidMappingTypeSignature;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\MappingLogicalException;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Exception\TypeErrorDuringMapping;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\RootNodeBuilder;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\TypeParser;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
/** @internal */
final class TypeTreeMapper implements TreeMapper
{
    public function __construct(private TypeParser $typeParser, private RootNodeBuilder $nodeBuilder)
    {
    }
    /** @pure */
    public function map(string $signature, mixed $source): mixed
    {
        $type = $this->typeParser->parse($signature);
        if ($type instanceof UnresolvableType) {
            throw new InvalidMappingTypeSignature($type);
        }
        try {
            $node = $this->nodeBuilder->build($source, $type);
        } catch (MappingLogicalException $exception) {
            throw new TypeErrorDuringMapping($type, $exception);
        }
        if (!$node->isValid()) {
            throw new TypeTreeMapperError($source, $type->toString(), $node->messages());
        }
        return $node->value();
    }
}
