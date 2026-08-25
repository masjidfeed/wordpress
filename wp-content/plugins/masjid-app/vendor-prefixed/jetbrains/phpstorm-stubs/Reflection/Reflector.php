<?php

namespace Masjid_App\Dependencies;

use Masjid_App\Dependencies\JetBrains\PhpStorm\Internal\LanguageLevelTypeAware;
/**
 * <b>Reflector</b> is an interface implemented by all
 * exportable Reflection classes.
 *
 * @link https://php.net/manual/en/class.reflector.php
 */
interface Reflector extends \Stringable
{
    /**
     * Exports a class.
     *
     * @link https://php.net/manual/en/reflector.export.php
     * @return string|null
     * @removed 7.4
     */
    public static function export();
    /**
     * Returns the string representation of any Reflection object.
     *
     * Please note that since PHP 8.0 this method is absent in this interface
     * and inherits from the {@see Stringable} parent.
     *
     * @return string
     */
    #[LanguageLevelTypeAware(['8.0' => 'string'], default: '')]
    public function __toString();
}
/**
 * <b>Reflector</b> is an interface implemented by all
 * exportable Reflection classes.
 *
 * @link https://php.net/manual/en/class.reflector.php
 */
\class_alias('Masjid_App\Dependencies\Reflector', 'Reflector', \false);
