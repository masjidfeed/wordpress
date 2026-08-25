<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth;

/**
 * @internal
 */
interface IsTenantAware
{
    public function tenantId(): ?string;
}
