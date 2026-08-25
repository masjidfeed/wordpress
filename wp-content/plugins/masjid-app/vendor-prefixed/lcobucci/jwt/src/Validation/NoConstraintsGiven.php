<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\Lcobucci\JWT\Validation;

use Masjid_App\Dependencies\Lcobucci\JWT\Exception;
use RuntimeException;
final class NoConstraintsGiven extends RuntimeException implements Exception
{
}
