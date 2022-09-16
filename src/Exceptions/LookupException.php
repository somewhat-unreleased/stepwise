<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Stepwise Lookup related exceptions
 */
class LookupException extends StepwiseException
{
    /**
     * Build an exception to throw when a Lookup's table's stubs couldn't be resolved fully
     *
     * @param string $tableName The name as far as it could be resolved.
     * @return static
     */
    public static function unresolvedStubs(string $tableName): self
    {
        return new static('Not all Lookup stubs could be resolved "'.$tableName.'"');
    }

    /**
     * Build an exception to throw when expected field/s are missing when populating a Lookup row
     *
     * @param array  $missing  The missing fields.
     * @param array  $expected The expected fields.
     * @param string $class    The lookup class being used.
     * @return static
     */
    public static function missingFields(array $missing, array $expected, string $class): self
    {
        return new static(
            'Field/s "'.implode('", "', $missing).'" are missing from row-data in '.$class.'. '
            .'The expected fields are: '.implode(', ', $expected)
        );
    }

    /**
     * Build an exception to throw when a unexpected field/s are present when populating a Lookup row
     *
     * @param array  $unexpected The unexpected fields.
     * @param array  $expected   The expected fields.
     * @param string $class      The lookup class being used.
     * @return static
     */
    public static function unexpectedFields(array $unexpected, array $expected, string $class): self
    {
        return new static(
            'Unexpected field/s "'.implode('", "', $unexpected).'" in '.$class.'. '
            .'The expected fields are: '.implode(', ', $expected)
        );
    }

    /**
     * Build an exception to throw when a no primary key is defined in a Lookup
     *
     * @param string $class The lookup class being used.
     * @return static
     */
    public static function noPrimaryKey(string $class): self
    {
        return new static('No primary-key fields were specified in class '.$class);
    }

    /**
     * Build an exception to throw when a particular field is used but a primary-key field is required
     *
     * @param string $fieldName The field being used.
     * @param string $class     The lookup class being used.
     * @return static
     */
    public static function fieldNotPrimary(string $fieldName, string $class): self
    {
        return new static('Field '.$fieldName.' is not a primary-key field in class '.$class);
    }

    /**
     * Build an exception to throw when a is field referenced but hasn't been defined
     *
     * @param string $fieldName The field being used.
     * @param string $class     The lookup class being used.
     * @return static
     */
    public static function fieldNotDefined(string $fieldName, string $class): self
    {
        return new static('Field definition for "'.$fieldName.'" doesn\'t exist in class '.$class);
    }

    /**
     * Build an exception to throw when a table couldn't be created
     *
     * @param string  $tableName   The table being created.
     * @param boolean $isTemporary Whether the table is temporary or not.
     * @param string  $class       The lookup class being used.
     * @return static
     */
    public static function couldNotCreateTable(string $tableName, bool $isTemporary, string $class): self
    {
        return new static('Could not create '.($isTemporary ? 'temporary ' : '').'table "'.$tableName.'" for '.$class);
    }

    /**
     * Build an exception to throw when populating a Lookup's table, and the given parameters are invalid
     *
     * eg. something like POINT(:long, :lat)).
     * @param string $class The lookup class being used.
     * @return static
     */
    public static function populateParametersInvalid(string $class): self
    {
        return new static('Invalid statement parameters were provided in class '.$class);
    }

    /**
     * Build an exception to throw when populating a Lookup's table, and instead of a value a statement is given
     * (represented by an array) but the params inside the statement don't match
     *
     * eg. something like POINT(:long, :lat)).
     * @param string $class The lookup class being used.
     * @return static
     */
    public static function populateStatementAndParamMismatch(string $class): self
    {
        return new static('Statement and parameters don\'t match in class '.$class);
    }

    /**
     * Build an exception to throw when populating a Lookup's table and a field is missing
     *
     * @param string $fieldName The missing field.
     * @param string $class     The lookup class being used.
     * @return static
     */
    public static function missingFieldValue(string $fieldName, string $class): self
    {
        return new static('Field "'.$fieldName.'" wasn\'t specified while populating a '.$class.' row');
    }
}
