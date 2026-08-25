<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Builder;

use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Configurator\MapAsBool;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Configurator\MapAsFloat;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Configurator\MapAsInt;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Configurator\MapAsString;
use Masjid_App\Dependencies\CuyZ\Valinor\Mapper\Tree\Shell;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\BooleanType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\FloatType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\IntegerType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\ScalarType;
use function assert;
use function is_int;
/** @internal */
final class ScalarNodeBuilder implements NodeBuilder
{
    public function build(Shell $shell): Node
    {
        assert($shell->type instanceof ScalarType);
        $value = $shell->value();
        // When the value is an integer and the type is a float, the value is
        // cast to float, to follow the rule of PHP regarding acceptance of an
        // integer value in a float type. Note that PHPStan/Psalm analysis
        // applies the same rule.
        if ($shell->type instanceof FloatType && is_int($value)) {
            $value = (float) $value;
        }
        if ($shell->type->accepts($value)) {
            return $shell->node($value);
        }
        $converter = match (\true) {
            $shell->allowCastingToBoolean !== [] && $shell->type instanceof BooleanType => static fn($value) => MapAsBool::convert($value, $shell->allowCastingToBoolean['true'], $shell->allowCastingToBoolean['false']),
            $shell->allowCastingToInteger && $shell->type instanceof IntegerType => MapAsInt::convert(...),
            $shell->allowCastingToFloat && $shell->type instanceof FloatType => MapAsFloat::convert(...),
            $shell->allowCastingToString => MapAsString::convert(...),
            default => null,
        };
        if ($converter) {
            $newValue = $converter($value);
            if ($shell->type->accepts($newValue)) {
                return $shell->node($newValue);
            }
        }
        return $shell->error($shell->type->errorMessage());
    }
}
