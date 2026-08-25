<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\FetchGooglePublicKeys;

use Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\FetchGooglePublicKeys;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Keys;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Error\FetchingGooglePublicKeysFailed;
interface Handler
{
    /**
     * @throws FetchingGooglePublicKeysFailed
     */
    public function handle(FetchGooglePublicKeys $action): Keys;
}
