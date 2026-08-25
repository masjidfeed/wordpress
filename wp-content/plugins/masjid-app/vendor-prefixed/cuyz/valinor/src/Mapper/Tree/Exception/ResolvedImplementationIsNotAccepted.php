<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Exception;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
/** @internal */
final class ResolvedImplementationIsNotAccepted extends RuntimeException
{
    public function __construct(string $name, Type $incorrectType)
    {
        parent::__construct("Invalid implementation type `{$incorrectType->toString()}`, expected a subtype of `{$name}`.");
    }
}
