<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
final readonly class PropertyDefinition
{
    public function __construct(
        /** @var non-empty-string */
        public string $name,
        /** @var non-empty-string */
        public string $signature,
        public Type $type,
        public Type $nativeType,
        public bool $hasDefaultValue,
        public mixed $defaultValue,
        public bool $isPublic,
        public Attributes $attributes
    )
    {
    }
}
