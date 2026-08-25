<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;

use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\ModifierTrait;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
final class Shallow implements Filter
{
    use ModifierTrait;
    public function modifyUri(UriInterface $uri): UriInterface
    {
        return $this->appendQueryParam($uri, 'shallow', 'true');
    }
}
