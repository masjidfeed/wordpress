<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\JWT\Contract;

trait KeysTrait
{
    /** @var array<non-empty-string, non-empty-string> */
    private array $values = [];
    /**
     * @return array<non-empty-string, non-empty-string>
     */
    public function all(): array
    {
        return $this->values;
    }
}
