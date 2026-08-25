<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Validator\Contracts;

use Masjid_App\Dependencies\StubTests\Framework\Storage\StubDataQueryInterface;
/**
 * Abstracts access to parsed stubs data.
 *
 * Mirrors ReflectionProviderInterface for the stubs side, allowing
 * validators and test infrastructure to be decoupled from RunnerScope.
 */
interface StubsProviderInterface
{
    public function getStubs(): StubDataQueryInterface;
}
