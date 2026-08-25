<?php

namespace Masjid_App\Dependencies\Beste\Cache;

final class InvalidArgument extends \InvalidArgumentException implements \Masjid_App\Dependencies\Psr\Cache\InvalidArgumentException
{
    public static function invalidKey(): self
    {
        return new self('The given key is not valid');
    }
}
