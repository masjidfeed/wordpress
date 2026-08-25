<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectType;
/** @internal */
final readonly class ClassDefinition
{
    public function __construct(
        /** @var class-string */
        public string $name,
        public ObjectType $type,
        public Attributes $attributes,
        public Properties $properties,
        public Methods $methods,
        public bool $isFinal,
        public bool $isAbstract
    )
    {
    }
}
