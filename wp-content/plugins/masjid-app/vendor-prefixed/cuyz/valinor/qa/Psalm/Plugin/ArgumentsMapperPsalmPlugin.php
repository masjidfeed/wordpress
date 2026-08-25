<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\QA\Psalm\Plugin;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\ArgumentsMapper;
use Masjid_App\Dependencies\Psalm\Internal\Type\Comparator\CallableTypeComparator;
use Masjid_App\Dependencies\Psalm\Plugin\EventHandler\Event\MethodReturnTypeProviderEvent;
use Masjid_App\Dependencies\Psalm\Plugin\EventHandler\MethodReturnTypeProviderInterface;
use Masjid_App\Dependencies\Psalm\Type\Atomic\TClosure;
use Masjid_App\Dependencies\Psalm\Type\Atomic\TKeyedArray;
use Masjid_App\Dependencies\Psalm\Type\Atomic\TMixed;
use Masjid_App\Dependencies\Psalm\Type\Union;
use function count;
use function reset;
final class ArgumentsMapperPsalmPlugin implements MethodReturnTypeProviderInterface
{
    public static function getClassLikeNames(): array
    {
        return [ArgumentsMapper::class];
    }
    public static function getMethodReturnType(MethodReturnTypeProviderEvent $event): ?Union
    {
        if ($event->getMethodNameLowercase() !== 'maparguments') {
            return null;
        }
        $arguments = $event->getCallArgs();
        if (count($arguments) === 0) {
            return null;
        }
        $types = $event->getSource()->getNodeTypeProvider()->getType($arguments[0]->value);
        if ($types === null) {
            return null;
        }
        $types = $types->getAtomicTypes();
        if (count($types) !== 1) {
            return null;
        }
        $type = reset($types);
        if ($type instanceof TKeyedArray) {
            // Internal class usage, see https://github.com/vimeo/psalm/issues/8726
            $type = CallableTypeComparator::getCallableFromAtomic($event->getSource()->getCodebase(), $type);
        }
        if (!$type instanceof TClosure) {
            return null;
        }
        $typeParams = $type->params ?? [];
        if ($typeParams === []) {
            return null;
        }
        $params = [];
        foreach ($typeParams as $param) {
            $params[$param->name] = $param->type ?? new Union([new TMixed()]);
        }
        return new Union([new TKeyedArray($params)]);
    }
}
