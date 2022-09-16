<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Input class related Stepwise exceptions
 */
class InputException extends StepwiseException
{
    /**
     * Build an exception to throw when a value doesn't exist
     *
     * @param string $name  The property name.
     * @param string $class The class being used.
     * @return static
     */
    public static function undefinedProperty(string $name, string $class): self
    {
        return new static('Undefined property: '.$class.'::$'.$name);
    }
}
