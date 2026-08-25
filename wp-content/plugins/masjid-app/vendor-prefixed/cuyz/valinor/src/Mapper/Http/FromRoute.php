<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Http;

use Attribute;
/**
 * Marks a parameter or property to be mapped from route values of an HTTP
 * request.
 *
 * For more information, {@see HttpRequest}.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
final class FromRoute
{
}
