<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Database\Query;

use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
interface Modifier
{
    /**
     * Modifies the given URI and returns it.
     */
    public function modifyUri(UriInterface $uri): UriInterface;
    /**
     * Modifies the given value and returns it.
     */
    public function modifyValue(mixed $value): mixed;
}
