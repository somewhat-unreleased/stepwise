<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Stepwise exceptions caused by clauses
 */
class ClauseException extends StepwiseException
{
    /**
     * Build an exception to throw when the operator must be equals
     *
     * @return static
     */
    public static function operatorMustBeEqualsForArray(): self
    {
        return new static('The operator must be \'=\' when specifying an array of values');
    }

    /**
     * Build an exception to throw when the values must be an array of 2 for BETWEEN
     *
     * @return static
     */
    public static function valuesMustBeArrayForBetween(): self
    {
        return new static('An array of two values must be given when using the BETWEEN operator');
    }

    /**
     * Build an exception to throw when a value given building a range isn't valid
     *
     * @param string $paramName The name of the invalid parameter.
     * @return static
     */
    public static function invalidRangeValueType(string $paramName): self
    {
        return new static($paramName.' must be int|float|null');
    }

    /**
     * Build an exception to throw when a field couldn't be found in the available tables
     *
     * @param string $field        The name of the field that couldn't be found.
     * @param array  $tableAliases The table that were checked.
     * @return static
     */
    public static function fieldNotFound(string $field, array $tableAliases): self
    {
        return new static(
            'The field "'.$field.'" couldn\'t be found in the given tables '
            .'('.implode(', ', $tableAliases).')'
        );
    }
}
