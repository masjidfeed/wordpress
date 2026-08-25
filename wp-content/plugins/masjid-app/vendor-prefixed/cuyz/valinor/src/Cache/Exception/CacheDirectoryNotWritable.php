<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Cache\Exception;

use RuntimeException;
/** @internal */
final class CacheDirectoryNotWritable extends RuntimeException
{
    public function __construct(string $directory)
    {
        parent::__construct("Provided directory `{$directory}` is not writable.");
    }
}
