<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Database\Query;

use Masjid_App\Dependencies\GuzzleHttp\Psr7\Query;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
use function array_merge;
/**
 * @internal
 */
trait ModifierTrait
{
    public function modifyValue(mixed $value): mixed
    {
        return $value;
    }
    protected function appendQueryParam(UriInterface $uri, string $key, mixed $value): UriInterface
    {
        $queryParams = array_merge(Query::parse($uri->getQuery()), [$key => $value]);
        $queryString = Query::build($queryParams);
        return $uri->withQuery($queryString);
    }
}
