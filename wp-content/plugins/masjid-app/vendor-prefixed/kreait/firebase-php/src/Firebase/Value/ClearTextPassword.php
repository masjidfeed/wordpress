<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Kreait\Firebase\Value;

use Masjid_App\Dependencies\Kreait\Firebase\Exception\InvalidArgumentException;
use Stringable;
use function mb_strlen;
/**
 * @internal
 */
final class ClearTextPassword
{
    /**
     * @var non-empty-string
     */
    public readonly string $value;
    private function __construct(string $value)
    {
        if ($value === '' || mb_strlen($value) < 6) {
            throw new InvalidArgumentException('A password must be a string with at least 6 characters.');
        }
        $this->value = $value;
    }
    public static function fromString(Stringable|string $value): self
    {
        return new self((string) $value);
    }
}
