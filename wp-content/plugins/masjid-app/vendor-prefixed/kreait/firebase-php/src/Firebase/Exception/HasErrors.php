<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Exception;

/**
 * @internal
 */
trait HasErrors
{
    /**
     * @var array<mixed>
     */
    protected array $errors = [];
    /**
     * @return array<mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
