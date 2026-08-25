<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\Callable;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use RuntimeException;
use function array_map;
use function implode;
/** @internal */
final class ExpectedReturnTypeAfterCallableColon extends RuntimeException implements InvalidType
{
    /**
     * @param list<Type> $parameters
     */
    public function __construct(array $parameters)
    {
        $parameters = implode(', ', array_map(fn(Type $type) => $type->toString(), $parameters));
        parent::__construct("Expected return type after `callable({$parameters}):`.");
    }
}
