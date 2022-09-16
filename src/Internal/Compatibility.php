<?php

namespace CodeDistortion\Stepwise\Internal;

use ReflectionMethod;
use ReflectionType;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Throwable;

/**
 * Some helper methods to help with compatibility
 */
class Compatibility
{

    /**
     * Allow access to the ReflectionType name
     *
     * @param ReflectionType $reflectionParameterType The ReflectionType to get the name of.
     * @return string
     */
    public static function parameterType(ReflectionType $reflectionParameterType)
    {
        // >= PHP 7.1
        if (method_exists($reflectionParameterType, 'getName')) {
            return $reflectionParameterType->getName();
        }

        // newer VarCloner
        $clone = (new VarCloner)->cloneVar($reflectionParameterType);
        try {
            return (string) $clone->name;
        } catch (Throwable $e) {
        }

        // older VarCloner
        return reset($clone->getRawData()[1]);
    }

    /**
     * Allow access to the ReflectionMethod return-type
     *
     * @param ReflectionMethod $reflectionMethod The reflection method to get the return type for.
     * @return string|null
     */
    public static function reflectionMethodReturnType(ReflectionMethod $reflectionMethod)
    {
        $returnType = $reflectionMethod->getReturnType();
        if (!$returnType) {
            return null;
        }
        if (method_exists($returnType, 'getName')) {
            return $returnType->getName();
        }
        return (string) $returnType;
    }
}
