<?php

declare (strict_types=1);
namespace Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\Factory;

use Masjid_App\Dependencies\CuyZ\Valinor\Type\Type;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\BooleanValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ClassStringType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\EnumType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\FloatValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\IntegerValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NativeClassType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\NullType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayElement;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\ShapedArrayType;
use Masjid_App\Dependencies\CuyZ\Valinor\Type\Types\StringValueType;
use Masjid_App\Dependencies\CuyZ\Valinor\Utility\Reflection\Reflection;
use UnitEnum;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
/** @internal */
final class ValueTypeFactory
{
    public static function from(mixed $value): Type
    {
        if ($value === null) {
            return NullType::get();
        }
        if (is_bool($value)) {
            return $value ? BooleanValueType::true() : BooleanValueType::false();
        }
        if (is_float($value)) {
            return new FloatValueType($value);
        }
        if (is_int($value)) {
            return new IntegerValueType($value);
        }
        if ($value instanceof UnitEnum) {
            return EnumType::fromPattern($value::class, $value->name);
        }
        if (is_string($value)) {
            if (Reflection::classOrInterfaceExists($value)) {
                return new ClassStringType([new NativeClassType($value)]);
            }
            return StringValueType::quoted($value);
        }
        if (is_array($value)) {
            $elements = [];
            foreach ($value as $key => $child) {
                $keyType = is_string($key) ? new StringValueType($key) : new IntegerValueType($key);
                $elements[$key] = new ShapedArrayElement($keyType, self::from($child));
            }
            return new ShapedArrayType($elements);
        }
        throw new CannotBuildTypeFromValue($value);
    }
}
