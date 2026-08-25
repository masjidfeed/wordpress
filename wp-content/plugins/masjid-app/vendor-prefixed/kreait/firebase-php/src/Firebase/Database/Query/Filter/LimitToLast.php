<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;

use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\ModifierTrait;
use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
final class LimitToLast implements Filter
{
    use ModifierTrait;
    private readonly int $limit;
    public function __construct(int $limit)
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Limit must be 1 or greater');
        }
        $this->limit = $limit;
    }
    public function modifyUri(UriInterface $uri): UriInterface
    {
        return $this->appendQueryParam($uri, 'limitToLast', $this->limit);
    }
}
