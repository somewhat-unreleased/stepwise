<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\SettingsException;

/**
 * Stepwise helper method
 */
class Helper
{
    /**
     * Check the given class exists and is of the correct type.
     *
     * @param string      $classFamily The name of the class family being checked.
     * @param string|null $class       The class to check.
     * @param string      $parentClass The class that $class should inherit from.
     * @return bool
     */
//    private static function checkClass(string $classFamily, ?string $class, string $parentClass): bool { // @TODO PHP 7.1
    public static function checkClass(string $classFamily, $class, string $parentClass): bool
    {
        if (!class_exists($class)) {
            throw SettingsException::missingClass($classFamily, $class);
        }
        if (!is_a($class, $parentClass, true)) {
            throw SettingsException::invalidClassType($classFamily, $class, $parentClass);
        }
        return true;
    }
}
