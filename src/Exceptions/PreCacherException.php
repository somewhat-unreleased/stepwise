<?php

namespace CodeDistortion\Stepwise\Exceptions;

/**
 * Stepwise PreCacher related exceptions
 */
class PreCacherException extends StepwiseException
{
    /**
     * Build an exception to throw when a the PreCacher can't find a particular loadAllXYZ method
     *
     * @param string $loadAllMethod The method that should exist.
     * @param string $idFieldName   The name of the value to be loaded (eg. 'productId')
     * @param string $class         The class being used.
     * @return static
     */
    public static function loadAllMethodMissing(string $loadAllMethod, string $idFieldName, string $class): self
    {
        return new static(
            'Cannot load all "'.$idFieldName.'s". '
            .'The '.$class.'->'.$loadAllMethod.'() method was not found'
        );
    }

    /**
     * Build an exception to throw when a the PreCacher runs a loadAllXYZ method and doesn't get an array response
     *
     * @param string $loadAllMethod The loadAllXYZ method that was called.
     * @param string $class         The class being used.
     * @return static
     */
    public static function loadAllMethodReturnInvalid(string $loadAllMethod, string $class): self
    {
        return new static('The return type of load-all method '.$loadAllMethod.'(..) must be array in '.$class);
    }

    /**
     * Build an exception to throw when a the PreCacher tries to resolve Lookup classes but no whitelist classes were
     * specified
     *
     * @return static
     */
    public static function noWhitelistClasses(): self
    {
        return new static('Please specify white-list Lookup classes (use \'all\' for all of them)');
    }

    /**
     * Build an exception to throw when a the PreCacher tries to resolve Lookup classes but the whitelist classes were
     * invalid
     *
     * @param array $whitelistClasses The whitelist classes used.
     * @return static
     */
    public static function invalidWhitelistClasses(array $whitelistClasses): self
    {
        return new static('Invalid white-list class/es "'.implode(',', $whitelistClasses).'"');
    }

    /**
     * Build an exception to throw when a the PreCacher tries to resolve Lookup classes but the blacklist classes were
     * invalid
     *
     * @param array $blacklistClasses The blacklist classes used.
     * @return static
     */
    public static function invalidBlacklistClasses(array $blacklistClasses): self
    {
        return new static('Invalid black-list class/es "'.implode(',', $blacklistClasses).'"');
    }



    /**
     * Build an exception to throw when a PreCacher class doesn't have any xyzPreCache methods
     *
     * @param string $class The class being used.
     * @return static
     */
    public static function noPreCacheMethods(string $class): self
    {
        return new static('No PreCache methods were found in '.$class);
    }

    /**
     * Build an exception to throw when a PreCacher xyzPreCache method doesn't give a valid response invalid
     *
     * @param string $method The xyzPreCache method.
     * @param string $class The class being used.
     * @return static
     */
    public static function invalidPreCacheMethodResponse(string $method, string $class): self
    {
        return new static('The return type of pre-cache method '.$method.'(..) must be void '.$class);
    }

    /**
     * Build an exception to throw when a PreCacher xyzPreCache method parameter isn't nullable
     *
     * @param string $paramName The name of the parameter.
     * @param string $method    The xyzPreCache method.
     * @param string $class     The class being used.
     * @return static
     */
    public static function preCacheParamNotNullable(string $paramName, string $method, string $class): self
    {
        return new static(
            'The "'.$paramName.'" Lookup parameter must be nullable '
            .'in preCache method '.$class.'->'.$method.'(..)'
        );
    }

    /**
     * Build an exception to throw when a PreCacher xyzPreCache method ID parameter isn't an array
     *
     * @param string $paramName    The name of the parameter.
     * @param string $existingType The current type of this parameter.
     * @param string $method       The xyzPreCache method.
     * @param string $class        The class being used.
     * @return static
     */
    public static function preCacheIdParamNotArray(
        string $paramName,
        string $existingType,
        string $method,
        string $class
    ): self {

        return new static(
            'The "'.$paramName.'" id-parameter must be an array (is currently "'.$existingType.'") '
            .'in preCache method '.$class.'->'.$method.'(..)'
        );
    }

    /**
     * Build an exception to throw when a PreCacher xyzPreCache method doesn't have any Lookup parameters
     *
     * @param string $method        The xyzPreCache method.
     * @param array  $currentParams The parameters currently in the method.
     * @param string $class         The class being used.
     * @return static
     */
    public static function noLookUpsInPreCacheMethod(string $method, array $currentParams, string $class): self
    {
        return new static(
            'PreCache method '.$class.'->'.$method.'(..) doesn\'t contain any Lookup parameters '
            .'(The present parameters are: \''.implode('\', \'', $currentParams).'\')'
        );
    }

    /**
     * Build an exception to throw when a PreCacher xyzPreCache method has more than one ID parameter
     *
     * @param string $method        The xyzPreCache method.
     * @param array  $currentParams The parameters currently in the method.
     * @param string $class         The class being used.
     * @return static
     */
    public static function multipleIdFieldsInPreCacheMethod(string $method, array $currentParams, string $class): self
    {
        return new static(
            'PreCache method '.$class.'->'.$method.'(..) must contain only one id field. '
            .'It currently contains: '.implode(', ', $currentParams)
        );
    }

    /**
     * Build an exception to throw when a Lookup is used in a xyzPreCache method, but it doesn't contain the necessary
     * ID field
     *
     * @param string $fieldName   The xyzPreCache method.
     * @param string $lookupClass The class being used.
     * @return static
     */
    public static function lookupTableMissingIdField(string $fieldName, string $lookupClass): self
    {
        return new static('Lookup '.$lookupClass.' does not contain field "'.$fieldName.'"');
    }

    /**
     * Build an exception to throw when trying to find xyzPreCache methods to deal with certain ID fields, but couldn't
     * find any
     *
     * @param array $fields The id fields to be used.
     * @param string $class The class being used.
     * @return static
     */
    public static function noPreCacheMethodsLeftOver(array $fields, string $class): self
    {
        return new static(
            'No PreCache methods were left in '.$class.' after removing ones that don\'t deal with fields: '
            .implode(', ', $fields)
        );
    }

    /**
     * Build an exception to throw when a beforeXYZPreCache hook method doesn't return void
     *
     * @param string $method The pre-cache hook method eg. beforeXYZPreCache.
     * @param string $class  The class being used.
     * @return static
     */
    public static function invalidPreCacheHookMethodReturnType(string $method, string $class): self
    {
        return new static('The return type of pre-cache-hook method '.$method.'(..) must be void in '.$class);
    }

    /**
     * Build an exception to throw when a beforeXYZPreCache hook method doesn't return void
     *
     * @param string $lookupClassName The parameter name used.
     * @param string $method          The pre-cache hook method eg. beforeXYZPreCache.
     * @param string $class           The class being used.
     * @return static
     */
    public static function invalidPreCacheHookMethodParamName(
        string $lookupClassName,
        string $method,
        string $class
    ): self {

        return new static(
            'The "'.$lookupClassName.'" parameter must be the name of an available Lookup '
            .'in '.$class.'->'.$method.'(..)'
        );
    }

    /**
     * Build an exception to throw when a beforeXYZPreCache hook method doesn't return void
     *
     * @param string $lookupClassName The Lookup class (ie. the parameter name) used.
     * @param string $type            The parameter's current type.
     * @param string $method          The pre-cache hook method eg. beforeXYZPreCache.
     * @param string $class           The class being used.
     * @return static
     */
    public static function invalidPreCacheHookMethodParamType(
        string $lookupClassName,
        string $type,
        string $method,
        string $class
    ): self {

        return new static(
            'The "'.$lookupClassName.'" parameter must be of type bool '
            .'(is currently "'.$type.'") '
            .'in '.$class.'->'.$method.'(..)'
        );
    }

}
