<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Contract;

use Masjid_App\Dependencies\Google\Cloud\Firestore\FirestoreClient;
interface Firestore
{
    public function database(): FirestoreClient;
}
