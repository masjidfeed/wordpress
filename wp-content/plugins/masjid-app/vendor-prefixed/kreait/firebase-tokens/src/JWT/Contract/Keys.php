<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract;

interface Keys
{
    /**
     * @return array<non-empty-string, non-empty-string>
     */
    public function all(): array;
}
