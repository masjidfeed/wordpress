<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
/** @internal */
final class CachedParser implements TypeParser
{
    /** @var array<string, Type> */
    private array $types = [];
    public function __construct(private TypeParser $delegate)
    {
    }
    public function parse(string $raw): Type
    {
        return $this->types[$raw] ??= $this->delegate->parse($raw);
    }
}
