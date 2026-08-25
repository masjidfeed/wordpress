<?php

namespace Masjid_App\Dependencies\StubTests\Framework\Validator;

use Masjid_App\Dependencies\StubTests\Framework\Storage\StubDataQueryInterface;
use Masjid_App\Dependencies\StubTests\Framework\Runner\RunnerScope;
use Masjid_App\Dependencies\StubTests\Framework\Validator\Contracts\StubsProviderInterface;
/**
 * Default implementation of StubsProviderInterface that uses Runner.
 *
 * Wraps RunnerScope::get()->getStubs() to allow dependency injection
 * in validator infrastructure while maintaining the existing production behavior.
 */
class RunnerStubsProvider implements StubsProviderInterface
{
    public function getStubs(): StubDataQueryInterface
    {
        return RunnerScope::get()->getStubs();
    }
}
