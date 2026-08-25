<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types;

use Masjid_App\Dependencies\CuyZ\Valinor\Compiler\Node;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\IsSingleton;
use function Masjid_App\Dependencies\CuyZ\Valinor\Compiler\value;
/** @internal */
final class MixedType implements Type
{
    use IsSingleton;
    public function accepts(mixed $value): bool
    {
        return \true;
    }
    public function compiledAccept(Node $node): Node
    {
        return value(\true);
    }
    public function matches(Type $other): bool
    {
        return $other instanceof self;
    }
    public function inferGenericsFrom(Type $other, Generics $generics): Generics
    {
        return $generics;
    }
    public function nativeType(): Type
    {
        return $this;
    }
    public function toString(): string
    {
        return 'mixed';
    }
}
