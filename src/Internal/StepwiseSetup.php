<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\SettingsException;
use ReflectionClass;

/**
 * Methods used to initialise the Stepwise object
*/
trait StepwiseSetup
{
    /**
     * Find the filter methods in this class
     *
     * ie. the "xxxFilter" (and "fallbackFilter") methods.
     * @param string $filterMethodSuffix   The "Filter" to look for at the end of filter-methods.
     * @param string $fallbackFilterMethod The name of the method to fall back to when no other filters are run.
     * @param array  $filterMethodSkipList The methods to skip.
     * @param string $actionCheckParam     The name of the $actionCheck parameter.
     * @param string $allowAlterParam      The name of the $allowAlter parameter.
     * @return array
     */
    private function findFilterMethods(
        string $filterMethodSuffix,
        string $fallbackFilterMethod,
        array $filterMethodSkipList,
        string $actionCheckParam,
        string $allowAlterParam
    ): array {

        // find the "xxxFilter" methods
        $filterMethodParams = [];
        $reflectionMethods = (new ReflectionClass(static::class))->getMethods();
        foreach ($reflectionMethods as $reflectionMethod) {

            // skip any in the blacklist
            $filterMethod = $reflectionMethod->name;
            if (in_array($filterMethod, $filterMethodSkipList)) {
                continue;
            }

            // ends in "Filter"?
            if ((mb_substr($filterMethod, -(mb_strlen($filterMethodSuffix))) == $filterMethodSuffix)
            // or is "fallbackFilter"
            || ($filterMethod == $fallbackFilterMethod)) {

                // pick out the parameters
                $parameters = [];
                foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
                    $parameters[$reflectionParameter->name] = $reflectionParameter;
                }



                // make sure the last parameter is $actionCheck
                $lastParam = array_pop($parameters);
                if (($lastParam) && ($lastParam->name != $actionCheckParam)) {
                    throw SettingsException::invalid2ndLastFilterParam(
                        static::class,
                        $filterMethod,
                        $actionCheckParam
                    );
                }

                // make sure the 2nd-last parameter is $allowAlter
                $lastParam = array_pop($parameters);
                if (($lastParam) && ($lastParam->name != $allowAlterParam)) {
                    throw SettingsException::invalidLastFilterParam(
                        static::class,
                        $filterMethod,
                        $allowAlterParam
                    );
                }

                // check that all non-array fields accept null
                foreach ($parameters as $reflectionParameter) {

                    // for compatibility with < php 7.1, ignore this check
                    $reflectionParameterType = $reflectionParameter->getType(); // for phpstan

                    $typeName = ($reflectionParameterType
                        ? Compatibility::parameterType($reflectionParameterType)
                        : null);
                    $allowsNull = ($reflectionParameterType ? $reflectionParameterType->allowsNull() : true);

                    if (($typeName != 'array') && (!$allowsNull)) {
                        throw SettingsException::notNullableFilterParam(
                            static::class,
                            $filterMethod,
                            $reflectionParameter->name
                        );
                    }
                }



                // store for later
                $filterMethodParams[$filterMethod] = $parameters;
            }
        }
        return $filterMethodParams;
    }

    /**
     * Check that the fields used by this class are all defined and set up properly
     *
     * @param array $fieldDefinitions The field definitions stored within this Stepwise.
     * @param array $lookupClasses    The Lookup classes this Stepwise uses.
     * @return array
     */
    private function resolveFieldDefinitions(array $fieldDefinitions, array $lookupClasses): array
    {
        $resolvedFieldDefinitions = $fieldDefinitions;

        foreach ($lookupClasses as $lookupClass) {
            foreach ($lookupClass::getFieldDefinitions() as $field => $definition) {

                // if it exists in this object too, check that it matches
                if (isset($resolvedFieldDefinitions[$field])) {
                    if ($definition !== $resolvedFieldDefinitions[$field]) {
                        throw SettingsException::conflictingFieldDefinition(
                            $field,
                            $resolvedFieldDefinitions[$field],
                            $definition,
                            $lookupClass
                        );
                    }
                    // if it's new, save the definition
                } else {
                    $resolvedFieldDefinitions[$field] = $definition;
                }
            }
        }
        return $resolvedFieldDefinitions;
    }
}
