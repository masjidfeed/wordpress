<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\CreateCustomToken;

use Masjid_App\Dependencies\Kreait\Firebase\JWT\Action\CreateCustomToken;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract\Token;
use Masjid_App\Dependencies\Kreait\Firebase\JWT\Error\CustomTokenCreationFailed;
interface Handler
{
    /**
     * @throws CustomTokenCreationFailed
     */
    public function handle(CreateCustomToken $action): Token;
}
