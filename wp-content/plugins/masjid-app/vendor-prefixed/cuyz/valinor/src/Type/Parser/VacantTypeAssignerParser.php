<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Parser\Exception\InvalidType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\UnresolvableType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\TypeHelper;
/** @internal */
final class VacantTypeAssignerParser implements TypeParser
{
    public function __construct(
        private TypeParser $delegate,
        /** @var array<non-empty-string, Type> */
        private array $vacantTypes
    )
    {
        if ($vacantTypes === []) {
            return;
        }
        foreach ($vacantTypes as $key => $vacantType) {
            try {
                $this->vacantTypes[$key] = TypeHelper::assignVacantTypes($vacantType, $vacantTypes);
            } catch (InvalidType $exception) {
                $this->vacantTypes[$key] = new UnresolvableType($vacantType->toString(), $exception->getMessage());
            }
        }
    }
    public function parse(string $raw): Type
    {
        $type = $this->delegate->parse($raw);
        if ($this->vacantTypes !== []) {
            $type = TypeHelper::assignVacantTypes($type, $this->vacantTypes);
        }
        return $type;
    }
}
