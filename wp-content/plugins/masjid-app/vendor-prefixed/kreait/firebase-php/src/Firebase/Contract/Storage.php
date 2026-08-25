<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use Masjid_App\Dependencies\Google\Cloud\Storage\Bucket;
use Masjid_App\Dependencies\Google\Cloud\Storage\StorageClient;
interface Storage
{
    public function getStorageClient(): StorageClient;
    public function getBucket(?string $name = null): Bucket;
}
