<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Cache;

use Masjid_App\Dependencies\CuyZ\Valinor\Definition\FunctionDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\FunctionDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Reflection;
/** @internal */
final class InMemoryFunctionDefinitionRepository implements FunctionDefinitionRepository
{
    /** @var array<string, FunctionDefinition> */
    private array $functionDefinitions = [];
    public function __construct(private FunctionDefinitionRepository $delegate)
    {
    }
    public function for(callable $function): FunctionDefinition
    {
        $reflection = Reflection::function($function);
        // @infection-ignore-all
        $key = $reflection->getFileName() . ':' . $reflection->getStartLine() . '-' . $reflection->getEndLine();
        return ($this->functionDefinitions[$key] ??= $this->delegate->for($function))->forCallable($function);
    }
}
