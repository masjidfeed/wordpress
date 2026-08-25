<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Cache;

use Masjid_App\Dependencies\CuyZ\Valinor\Cache\Cache;
use Masjid_App\Dependencies\CuyZ\Valinor\Cache\CacheEntry;
use Masjid_App\Dependencies\CuyZ\Valinor\Cache\TypeFilesWatcher;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\ClassDefinition;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\Cache\Compiler\ClassDefinitionCompiler;
use Masjid_App\Dependencies\CuyZ\Valinor\Definition\Repository\ClassDefinitionRepository;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ObjectType;
/** @internal */
final class CompiledClassDefinitionRepository implements ClassDefinitionRepository
{
    public function __construct(
        private ClassDefinitionRepository $delegate,
        /** @var Cache<ClassDefinition> */
        private Cache $cache,
        private TypeFilesWatcher $filesWatcher,
        private ClassDefinitionCompiler $compiler
    )
    {
    }
    public function for(ObjectType $type): ClassDefinition
    {
        // @infection-ignore-all
        $key = "class-definition-\x00" . $type->toString();
        $entry = $this->cache->get($key);
        if ($entry) {
            return $entry;
        }
        $class = $this->delegate->for($type);
        $code = 'fn () => ' . $this->compiler->compile($class);
        $filesToWatch = fn() => $this->filesWatcher->for($type);
        $this->cache->set($key, new CacheEntry($code, $filesToWatch));
        /** @var ClassDefinition */
        return $this->cache->get($key);
    }
}
