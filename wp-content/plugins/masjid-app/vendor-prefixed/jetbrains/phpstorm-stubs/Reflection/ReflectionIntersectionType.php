<?php

namespace Masjid_App\Dependencies;

use Masjid_App\Dependencies\JetBrains\PhpStorm\Pure;
/**
 * @since 8.1
 */
class ReflectionIntersectionType extends \ReflectionType
{
    /** @return ReflectionType[] */
    #[Pure]
    public function getTypes(): array
    {
    }
}
/**
 * @since 8.1
 */
\class_alias('Masjid_App\Dependencies\ReflectionIntersectionType', 'ReflectionIntersectionType', \false);
