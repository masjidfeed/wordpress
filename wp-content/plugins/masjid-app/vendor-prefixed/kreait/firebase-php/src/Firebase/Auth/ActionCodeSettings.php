<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Auth;

interface ActionCodeSettings
{
    /**
     * @return array<non-empty-string, bool|string>
     */
    public function toArray(): array;
}
