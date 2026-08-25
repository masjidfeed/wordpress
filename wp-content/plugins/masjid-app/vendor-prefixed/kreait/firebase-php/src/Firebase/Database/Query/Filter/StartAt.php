<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;

use Masjid_App\Dependencies\Beste\Json;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\Filter;
use Masjid_App\Dependencies\Kreait\Firebase\Database\Query\ModifierTrait;
use Masjid_App\Dependencies\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
final class StartAt implements Filter
{
    use ModifierTrait;
    public function __construct(private readonly int|float|string|bool $value)
    {
    }
    public function modifyUri(UriInterface $uri): UriInterface
    {
        return $this->appendQueryParam($uri, 'startAt', Json::encode($this->value));
    }
}
