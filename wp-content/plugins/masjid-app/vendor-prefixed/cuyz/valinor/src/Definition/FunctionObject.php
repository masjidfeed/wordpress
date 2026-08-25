<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition;

/** @internal */
final readonly class FunctionObject
{
    public function __construct(
        public FunctionDefinition $definition,
        /** @var callable */
        public mixed $callback
    )
    {
    }
}
