<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Cache;

/** @internal */
final readonly class CacheEntry
{
    public function __construct(
        public string $code,
        /** @var null|callable(): list<non-empty-string> */
        public mixed $filesToWatch = null
    )
    {
    }
}
