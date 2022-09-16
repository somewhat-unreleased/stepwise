<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Stepwise exception thrown when something is wrong with configuration
 */
class SettingsException extends StepwiseException
{
    /**
     * Build an exception to throw when a class is missing
     *
     * @param string      $classFamily The type of class being checked (eg. PreCacher).
     * @param string|null $class       The PreCacher class used.
     * @return static
     */
//    public static function missingClass(string $classFamily, ?string $class): self // @TODO PHP 7.1
    public static function missingClass(string $classFamily, $class): self
    {
        return new static($classFamily.' class "'.$class.'" does not exist');
    }

    /**
     * Build an exception to throw when a class doesn't inherit from the required class
     *
     * @param string $classFamily The type of class being checked (eg. PreCacher).
     * @param string $class       The class being checked.
     * @param string $parentClass The class that $class should extend from.
     * @return static
     */
    public static function invalidClassType(string $classFamily, string $class, string $parentClass): self
    {
        return new static($classFamily.' class "'.$class.'" must extend from '.$parentClass);
    }


    /**
     * Build an exception to throw when no Lookup classes were defined
     *
     * @return static
     */
    public static function noLookupClasses(): self
    {
        return new static('No Lookup classes were defined');
    }


    /**
     * Build an exception to throw when the Lookup class is invalid
     *
     * @param string $field                 The name of the field being defined.
     * @param string $originalDefinition    The original field definition.
     * @param string $conflictingDefinition The conflicting field definition.
     * @param string $class                 The class the definition came from.
     * @return static
     */
    public static function conflictingFieldDefinition(
        string $field,
        string $originalDefinition,
        string $conflictingDefinition,
        string $class
    ): self {

        return new static(
            'The field definition "'.$conflictingDefinition.'" '
            .'for field "'.$field.'" '
            .'doesn\'t match "'.$originalDefinition.'" '
            .'in class '.$class
        );
    }



    /**
     * Build an exception to throw when a Lookup table-name stub can't be resolved
     *
     * @param string $name      The stub being resolved.
     * @param string $configKey The config-key being used to resolve the stub.
     * @return static
     */
    public static function unresolvableStub(string $name, string $configKey): self
    {
        return new static(
            'Cannot determine replacement for Lookup stub "'.$name.'" '
            .'(does config-key "'.$configKey.'" exist?)'
        );
    }



    /**
     * Build an exception to throw when the 2nd-last parameter of a filter-method isn't correct
     *
     * @param string $class        The Stepwise class the filter-method is in.
     * @param string $filterMethod The filter method being checked.
     * @param string $paramName    The name of the 2nd-last parameter.
     * @return static
     */
    public static function invalid2ndLastFilterParam(string $class, string $filterMethod, string $paramName): self
    {
        return new static('The second-last parameter of '.$class.'::'.$filterMethod.'(..) must be: $'.$paramName);
    }

    /**
     * Build an exception to throw when the last parameter of a filter-method isn't correct
     *
     * @param string $class        The Stepwise class the filter-method is in.
     * @param string $filterMethod The filter method being checked.
     * @param string $paramName    The name of the last parameter.
     * @return static
     */
    public static function invalidLastFilterParam(string $class, string $filterMethod, string $paramName): self
    {
        return new static('The last parameter of '.$class.'::'.$filterMethod.'(..) must be: $'.$paramName);
    }

    /**
     * Build an exception to throw when the parameter of a filter-method isn't nullable
     *
     * @param string $class        The Stepwise class the filter-method is in.
     * @param string $filterMethod The filter method being checked.
     * @param string $paramName    The name of the parameter.
     * @return static
     */
    public static function notNullableFilterParam(string $class, string $filterMethod, string $paramName): self
    {
        return new static(
            'Parameter "'.$paramName.'" '
            .'of filter-method '.$class.'::'.$filterMethod.'(..) '
            .'must be nullable'
        );
    }



    /**
     * Build an exception to throw when the parameter of a filter-method isn't nullable
     *
     * @param mixed  $searchInput The input given.
     * @param string $inputClass  The Input class the given input should be an instance of.
     * @return static
     */
    public static function invalidInputClass($searchInput, string $inputClass): self
    {
        return new static(
            'The Input given must be either an array or a '.$inputClass.'.'
            .(is_object($searchInput) ? ' '.get_class($searchInput).' was given' : '')
        );
    }



    /**
     * Build an exception to throw when a Lookup class is missing a primary-key
     *
     * @param string $lookupClass The class missing a primary-key.
     * @return static
     */
    public static function noLookupTablePrimaryKey(string $lookupClass): self
    {
        return new static('No primary-key was defined in '.$lookupClass);
    }



    /**
     * Build an exception to throw when a Lookup class is missing a primary-key
     *
     * @param string $indexType   The type of index (primary, unique, regular, fulltext).
     * @param string $field       The field that wasn't found.
     * @param string $lookupClass The class with the erroneous index field.
     * @return static
     */
    public static function indexFieldNotFound(string $indexType, string $field, string $lookupClass): self
    {
        return new static($indexType.' field "'.$field.'" is not an available field in '.$lookupClass);
    }
}
