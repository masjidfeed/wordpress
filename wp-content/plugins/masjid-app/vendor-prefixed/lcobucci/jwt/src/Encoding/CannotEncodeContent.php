<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Encoding;

use JsonException;
use Masjid_App\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;
final class CannotEncodeContent extends RuntimeException implements Exception
{
    public static function jsonIssues(JsonException $previous): self
    {
        return new self(message: 'Error while encoding to JSON', previous: $previous);
    }
}
