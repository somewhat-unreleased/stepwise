<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Exceptions\LookupException;
use CodeDistortion\Stepwise\Exceptions\PreCacherException;
use CodeDistortion\Stepwise\Exceptions\SettingsException;
use CodeDistortion\Stepwise\Internal\Compatibility;
use CodeDistortion\Stepwise\Internal\Helper;
use CodeDistortion\Stepwise\Internal\QueryTracker;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionException;

/**
 * This class is used to create and populate Lookup's database tables
 */
abstract class PreCacher
{
    /**
     * The Stepwise class this PreCacher belongs to
     *
     * @var string
     */
    protected $stepwiseClass;

    /**
     * Methods to skip when looking for xxxPreCache methods
     *
     * @var array
     */
    protected $methodSkipList = [];





    /**
     * The suffix to methods that perform some pre-caching (eg. productPreCache)
     *
     * @var string
     */
    private $methodSuffix = 'PreCache';

    /**
     * The name of the method to run before any caching takes place
     *
     * @var string
     */
    private $beforeHook = 'beforePreCache';

    /**
     * The name of the method to run after all caching has finished
     *
     * @var string
     */
    private $afterHook = 'afterPreCache';

    /**
     * Prefix for methods to call before a particular xxxPreCache method is run
     *
     * eg. beforeProductPreCache
     * @var string
     */
    private $beforeHookPrefix = 'before';

    /**
     * Prefix for methods to call after a particular xxxPreCache method has finished running
     *
     * eg. afterProductPreCache
     * @var string
     */
    private $afterHookPrefix = 'after';

    /**
     * Prefix for methods that load all of something
     *
     * These methods are needed when an xxxPreCache method uses an id field in it's parameter list (eg. $makerIds)
     * eg. loadAllMakerIds
     * @var string
     */
    private $loadAllMethodPrefix = 'loadAll';





    /**
     * Do extra things to help with testing
     *
     * @var boolean
     */
    private $testMode = false;

    /**
     * Should the queries actually be run?
     *
     * @var boolean
     */
    private $runQueries = true;

    /**
     * Keeps track of the queries that were generated (used by testing code)
     *
     * @var QueryTracker|null
     */
    private $queryTracker = null;





    /**
     * An internal cache of the available Lookup class names
     *
     * @var array
     */
    private $resolvedLookupClasses = [];





    /**
     * Constructor
     */
    public function __construct()
    {
        $this->selfTest();
        $this->recordAllLookups();
    }

    /**
     * Test that this class has been set up properly
     *
     * @return void
     * @throws SettingsException Thrown when something has been set up incorrectly.
     */
//    public function selfTest(): void // @TODO PHP 7.1
    public function selfTest()
    {
        Helper::checkClass('Stepwise', $this->stepwiseClass, Stepwise::class);
    }



    /**
     * Turn test-mode on - which does extra things for testing code to check
     *
     * @param boolean $testMode Turn the setting on or off.
     * @return self
     */
    public function testMode(bool $testMode = true): self
    {
        $this->testMode = $testMode;

        return $this; // chainable
    }

    /**
     * Should queries actually be run?
     *
     * @param boolean $runQueries Turn the setting on or off.
     * @return self
     */
    public function setRunQueries(bool $runQueries): self
    {
        $this->runQueries = $runQueries;

        return $this; // chainable
    }

    /**
     * Let the caller pass a queryTracker to track queries with
     *
     * @param QueryTracker $queryTracker The tracker to track queries with.
     * @return self
     */
//    public function setQueryTracker(?QueryTracker $queryTracker): self // @TODO PHP 7.1
    public function setQueryTracker(QueryTracker $queryTracker = null): self
    {
        $this->queryTracker = $queryTracker;

        return $this; // chainable
    }


    /**
     * Take input that's easy to obtain from a console command, and create the desired tables in the database
     *
     * @param string|null $whitelistClassesParam Comma separated string of classes to include ('all' for all of them).
     * @param string|null $blacklistClassesParam Comma separated string of classes to exclude.
     * @param array       $tableNameReplacements Replacement strings that will be used when generating the table-name.
     * @return self
     * @throws PreCacherException Thrown if any given Lookup classes aren't valid.
     */
    public function createTablesFromCommand(
//        ?string $whitelistClassesParam, // @TODO PHP 7.1
        string $whitelistClassesParam = null,
//        ?string $blacklistClassesParam, // @TODO PHP 7.1
        string $blacklistClassesParam = null,
        array $tableNameReplacements
    ): self {

        // resolve which tables to create
        $lookupClasses = $this->resolveLookupClasses(
            array_filter(explode(',', (string) $whitelistClassesParam)),
            array_filter(explode(',', (string) $blacklistClassesParam))
        );

        // create these tables
        return $this->createTables($lookupClasses, $tableNameReplacements);
    }

    /**
     * Take the given list of Lookup classes and create their tables in the database
     *
     * @param array $lookupClasses         The Lookup classes' db tables to create.
     * @param array $tableNameReplacements The db table string replacements to use.
     * @return self
     * @throws PreCacherException Thrown if any given Lookup classes aren't valid.
     */
    public function createTables(array $lookupClasses, array $tableNameReplacements): self
    {
        $lookupClasses = $this->resolveLookupClasses($lookupClasses);
        foreach ($lookupClasses as $lookupClass) {

            $class = $this->resolvedLookupClasses[$lookupClass];
            $lookup = (new $class($tableNameReplacements))
                ->setRunQueries($this->runQueries)
                ->setQueryTracker($this->queryTracker)
                ->testMode($this->testMode);
            $lookup->createPrimaryTable();
        }

        return $this; // chainable
    }





    /**
     * Take input that's easy to obtain from a console command, and run the necessary pre-cache methods
     *
     * @param string|null $whitelistClassesParam Comma separated string of classes to include ('all' for all of them).
     * @param string|null $blacklistClassesParam Comma separated string of classes to exclude.
     * @param array       $updateIds             Assoc array of fieldnames and values that pre-caching shall run for.
     * @param array       $tableNameReplacements Replacement strings that will be used when generating the lookup-table
     *                                           name.
     * @param boolean     $thrashTestMode        Allow lookup-table to detect and report thrashing.
     * @return self
     * @throws PreCacherException Thrown if any given Lookup classes aren't valid.
     * @throws PreCacherException Thrown when a "LoadAllXYZs" method isn't present or returns an invalid value.
     * @throws LookupException    Thrown when there is a problem creating or updating a Lookup table in the database.
     */
    public function populateTablesFromCommand(
//        ?string $whitelistClassesParam, // @TODO PHP 7.1
        string $whitelistClassesParam = null,
//        ?string $blacklistClassesParam, // @TODO PHP 7.1
        string $blacklistClassesParam = null,
        array $updateIds = [],
        array $tableNameReplacements = [],
        bool $thrashTestMode = false
    ): self {

        // resolve which tables to create
        $lookupClasses = $this->resolveLookupClasses(
            array_filter(explode(',', (string) $whitelistClassesParam)),
            array_filter(explode(',', (string) $blacklistClassesParam))
        );

        // perform pre-caching for these tables
        return $this->populateTables($lookupClasses, $updateIds, $tableNameReplacements, $thrashTestMode);
    }

    /**
     * Perform the pre-caching process for the desired Lookup classes
     *
     * @param array   $lookupClasses         An array of the Lookup class names to pre-cache for.
     * @param array   $updateIds             Assoc array of field-names and values that pre-caching shall run for.
     * @param array   $tableNameReplacements Replacement strings that will be used when generating the lookup-table
     *                                       name.
     * @param boolean $thrashTestMode        Allow lookup-table to detect and report thrashing.
     * @return self
     * @throws PreCacherException  Thrown when a "LoadAllXYZs" method isn't present or returns an invalid value.
     * @throws LookupException     Thrown when there is a problem creating or updating a Lookup table in the database.
     */
    public function populateTables(
        array $lookupClasses = [],
        array $updateIds = [],
        array $tableNameReplacements = [],
        bool $thrashTestMode = false
    ): self {

        // check the lookup-tables to populate
        $lookupClasses = $this->resolveLookupClasses($lookupClasses);

        $idsPerIteration = 25;
        $updateIds = $this->tweakUpdateIds($updateIds);
        $methods = $this->pickPreCacheMethods($lookupClasses, $updateIds, $tableNameReplacements, $thrashTestMode);

        if (!count($methods)) {
            return $this; // chainable
        }

        // put the parameters for each method together to pass to the before/afterPreCache below
        $allMethodParams = [];
        foreach ($methods as $method => $parameters) {
            $allMethodParams = array_merge($allMethodParams, $parameters);
        }

        // call the "beforePreCache" method if it exists
        $this->callPreCacheHook($this->beforeHook, $allMethodParams);



        foreach ($methods as $method => $parameters) {

            $method = (string) $method; // for phpstan

            // pick out the ids that will be updated
            $myUpdateIds = [];
            $idFieldName = null;
            foreach ($parameters as $fieldName) {
                if ((!is_null($fieldName)) && (!$fieldName instanceof Lookup)) {

                    $idFieldName = $fieldName;
                    if (isset($updateIds[$fieldName])) {
                        $myUpdateIds[$fieldName] = $updateIds[$fieldName];
                        break;
                    }
                }
            }



            // determine which ids to loop through
            $loopIds = [];
            if ($idFieldName) {

                // make sure the "LoadAllXYZs" method exists
                $loadAllMethod = $this->loadAllMethodPrefix.Str::studly($idFieldName.'s');
                if (!method_exists(static::class, $loadAllMethod)) {
                    throw PreCacherException::loadAllMethodMissing($loadAllMethod, $idFieldName, static::class);
                }

                // make sure it's return type is ok
                try {
                    $reflectionMethod = (new ReflectionClass(static::class))->getMethod($loadAllMethod);
                } catch (ReflectionException $e) {
                    // $e is thrown when the method is missing, but this was checked above
                }
                if (Compatibility::reflectionMethodReturnType($reflectionMethod) != 'array') {
                    throw PreCacherException::loadAllMethodReturnInvalid($loadAllMethod, static::class);
                }

                // use the ids that were passed by the caller
                // (eg. we're only updating products belonging to one maker)
                if (isset($myUpdateIds[$idFieldName])) {
                    $loopIds = $myUpdateIds[$idFieldName];
                // or look for the method that can load the ids
                } else {
                    $loopIds = $this->$loadAllMethod();
                }
            }



            // call the "beforeXYZPreCache" method if it exists
            $this->callPreCacheHook($this->beforeHookPrefix.ucfirst($method), $parameters);



            // tell each Lookup the ids of the rows that will be updated
            foreach ($parameters as $lookup) {
                if ($lookup instanceof Lookup) {
                    if ($myUpdateIds) {
                        $lookup->startPopulateProcess()->willUpdate($myUpdateIds);
                    } else {
                        $lookup->startPopulateProcess()->willUpdateEverything();
                    }
                }
            }

            // call the pre-cache method - looping through the ids
            if ($idFieldName) {
                foreach (array_chunk($loopIds, $idsPerIteration) as $chunkedMakerIds) {
                    $parameters[$idFieldName] = $chunkedMakerIds;

                    $callable = [$this, $method];
                    if (is_callable($callable)) { // to please phpstan
                        call_user_func_array($callable, array_values($parameters));
                    }
                }
            // or call the pre-cache method when it doesn't have ids
            } else {
                $callable = [$this, $method];
                if (is_callable($callable)) { // to please phpstan
                    call_user_func_array($callable, array_values($parameters));
                }
            }

            // process any left-over rows, delete the temp tables
            foreach ($parameters as $parameter) {
                if ($parameter instanceof Lookup) {
                    $parameter->finishPopulateProcess();
                }
            }



            // call the "afterXYZPreCache" method if it exists
            $this->callPreCacheHook($this->afterHookPrefix.ucfirst($method), $parameters);
        }



        // call the "afterPreCache" method if it exists
        $this->callPreCacheHook($this->afterHook, $allMethodParams);

        return $this; // chainable
    }




    /**
     * Generate an internal list of the available Lookup classes
     *
     * @return void
     */
//    private function recordAllLookups(): void // @TODO PHP 7.1
    private function recordAllLookups()
    {
        // work out which Lookups can be used
        $resolvedLookupClasses = [];
        foreach ($this->stepwiseClass::getLookupClasses() as $lookupClass) {
            $parts = explode('\\', $lookupClass);
            $className = (string) array_pop($parts);
            $resolvedLookupClasses[$className] = $lookupClass;
        }

        $this->resolvedLookupClasses = $resolvedLookupClasses;
    }

    /**
     * Take the given white and black lists of Lookup classes and work out which ones should be left
     *
     * @param array $whitelistClasses Array af Lookup classes to include in the list (['all'] for all of them).
     * @param array $blacklistClasses Array of Lookup classes to exclude from the list.
     * @return array
     * @throws PreCacherException Thrown if any given Lookup classes aren't valid.
     */
    private function resolveLookupClasses(array $whitelistClasses = [], array $blacklistClasses = []): array
    {
        if (!count($whitelistClasses)) {
            throw PreCacherException::noWhitelistClasses();
        }
        // allow for "all" to represent all of the classes
        $whitelistClasses = ($whitelistClasses == ['all'] ? [] : $whitelistClasses);



        // if no white-list classes where specified, use them all
        $whitelistClasses = (count($whitelistClasses)
            ? $whitelistClasses
            : array_keys($this->resolvedLookupClasses));

        // pick out the white-list classes
        $lookupClasses = [];
        foreach ($whitelistClasses as $index => $className) {
            $className = ucfirst($className);
            if (isset($this->resolvedLookupClasses[$className])) {
                $lookupClasses[$className] = $className;
                unset($whitelistClasses[$index]); // look for left-overs below
            }
        }

        // remove the black-list classes
        foreach ($blacklistClasses as $index => $className) {
            $className = ucfirst($className);
            if (isset($this->resolvedLookupClasses[$className])) {
                unset($lookupClasses[$className]);
                unset($blacklistClasses[$index]); // look for left-overs below
            }
        }

        // make sure there aren't any left-over white / black list classes that couldn't be resolved
        if (count($whitelistClasses)) {
            throw PreCacherException::invalidWhitelistClasses($whitelistClasses);
        }
        if (count($blacklistClasses)) {
            throw PreCacherException::invalidBlacklistClasses($blacklistClasses);
        }

        return array_values($lookupClasses);
    }

    /**
     * Prepare a list of pre-cache methods to run
     *
     * @param array   $lookupClasses         An array of the Lookup class names to pre-cache for.
     * @param array   $updateIds             Assoc array of field-names and values that pre-caching shall run for.
     * @param array   $tableNameReplacements Replacement strings that will be used when generating the lookup-table
     *                                       name.
     * @param boolean $thrashTestMode        Allow lookup-table to detect and report thrashing.
     * @return array
     * @throws PreCacherException Thrown when the PreCache methods or their parameters are invalid in some way.
     */
    private function pickPreCacheMethods(
        array $lookupClasses = [],
        array $updateIds = [],
        array $tableNameReplacements = [],
        bool $thrashTestMode = false
    ): array {

        // find the "xxxPreCache" methods
        $methods = [];
        foreach ((new ReflectionClass(static::class))->getMethods() as $reflectionMethod) {

            // skip any in the skip-list
            $method = $reflectionMethod->name;
            if (!in_array($method, $this->methodSkipList)) {

                // ends in "PreCache"?
                if (mb_substr($method, -mb_strlen($this->methodSuffix)) == $this->methodSuffix) {

                    // ignore ones that start with "before" or "after"
                    if ((mb_substr($method, 0, mb_strlen($this->beforeHookPrefix)) != $this->beforeHookPrefix)
                    && (mb_substr($method, 0, mb_strlen($this->afterHookPrefix)) != $this->afterHookPrefix)) {
                        $methods[$method] = $reflectionMethod;
                    }
                }
            }
        }

        // split the parameters up into Lookup and id ones
        $allParams = $lookupParams = $idParams = [];
        foreach ($methods as $method => $reflectionMethod) {

            // make sure it's return type is ok
            if (!in_array(Compatibility::reflectionMethodReturnType($reflectionMethod), ['void', null], true)) {
                throw PreCacherException::invalidPreCacheMethodResponse($method, static::class);
            }

            $allParams[$method] = $lookupParams[$method] = $idParams[$method] = [];
            foreach ($reflectionMethod->getParameters() as $reflectionParameter) {

                $reflectionParameterType = $reflectionParameter->getType(); // for phpstan

                // is this a lookup-table parameter?
                $paramName = $reflectionParameter->name;
                $className = ucfirst($paramName);
                if (isset($this->resolvedLookupClasses[$className])) {

                    // check that the lookup-table param is nullable
                    $allowsNull = ($reflectionParameterType ? $reflectionParameterType->allowsNull() : true);
                    if (!$allowsNull) {
                        throw PreCacherException::preCacheParamNotNullable($paramName, $method, static::class);
                    }

                    // create the lookup table object
                    $allParams[$method][$className]
                    = $lookupParams[$method][$className]
                    = (new $this->resolvedLookupClasses[$className]($tableNameReplacements, $thrashTestMode))
                        ->setRunQueries($this->runQueries)
                        ->setQueryTracker($this->queryTracker)
                        ->testMode($this->testMode);

                // or an id parameter?
                } else {

                    $typeName = ($reflectionParameterType
                        ? Compatibility::parameterType($reflectionParameterType)
                        : null);
                    if ($typeName != 'array') {
                        throw PreCacherException::preCacheIdParamNotArray(
                            $paramName,
                            $typeName,
                            $method,
                            static::class
                        );
                    }

                    // remove one trailing 's'
                    if (strtolower(mb_substr($paramName, -1)) == 's') {
                        $paramName = mb_substr($paramName, 0, -1);
                    }
                    $paramName = Str::camel($paramName);
                    $fieldName = Str::snake($paramName);

                    $allParams[$method][$fieldName]
                    = $idParams[$method][$fieldName]
                    = $fieldName;
                }
            }
        }

        // make sure some methods were found
        if (!count($allParams)) {
            throw PreCacherException::noPreCacheMethods(static::class);
        }

        // check that at least one Lookup has been specified in each method
        foreach (array_keys($allParams) as $method) {
            if (!count($lookupParams[$method])) {
                throw PreCacherException::noLookUpsInPreCacheMethod(
                    $method,
                    array_keys($allParams[$method]),
                    static::class
                );
            }
        }

        // check that each pre-cache method has only one id field
        foreach (array_keys($allParams) as $method) {
            if (count($idParams[$method]) > 1) {
                throw PreCacherException::multipleIdFieldsInPreCacheMethod(
                    $method,
                    $idParams[$method],
                    static::class
                );
            }
        }

        // check to make sure the id-parameters (eg. makerId) exist as fields in each of that method's lookup-tables
        foreach (array_keys($allParams) as $method) {
            foreach ($idParams[$method] as $fieldName) {

                foreach ($lookupParams[$method] as $className => $lookup) {
                    if (!$lookup->hasField($fieldName)) {
                        throw PreCacherException::lookupTableMissingIdField($fieldName, $className);
                    }
                }
            }
        }

        // strip any methods out that don't match the ids to update
        foreach ($updateIds as $fieldName => $values) {
            foreach (array_keys($allParams) as $method) {
                if (!in_array($fieldName, $idParams[$method])) {
                    unset($allParams[$method], $lookupParams[$method], $idParams[$method]);
                }
            }
        }

        // make sure some methods were left
        if (!count($allParams)) {
            throw PreCacherException::noPreCacheMethodsLeftOver(array_keys($updateIds), static::class);
        }

        // now only pick methods that populate the desired lookup-tables
        foreach (array_keys($allParams) as $method) {

            foreach ($lookupParams[$method] as $className => $lookup) {

                // pass null instead if this lookup table isn't needed
                if (!in_array($className, $lookupClasses)) {
                    $allParams[$method][$className] = $lookupParams[$method][$className] = null;
                }
            }

            // remove this method if there aren't any lookup-tables left
            if (!count(array_filter($lookupParams[$method]))) {
                unset($allParams[$method], $lookupParams[$method], $idParams[$method]);
            }
        }

        return $allParams;
    }

    /**
     * Take the array input ids to perform caching for, and format their field names into snake_case ready for use
     * internally
     *
     * @param array $updateIds The input array of id fields values to run pre-caching for.
     * @return array
     */
    private function tweakUpdateIds(array $updateIds): array
    {
        $updateIds2 = [];
        foreach ($updateIds as $fieldName => $values) {
            $fieldName = (string) $fieldName; // for phpstan

            // remove one trailing 's' from the field name
            if (strtolower(mb_substr($fieldName, -1)) == 's') {
                $fieldName = mb_substr($fieldName, 0, -1);
            }
            $fieldName = Str::snake($fieldName);

            // when populated from the command line, these will be csv-strings
            if (is_string($values)) {
                $values = explode(',', $values);
            }
            if ((is_array($values)) && (count($values))) {
                $updateIds2[$fieldName] = $values;
            }
        }
        return $updateIds2;
    }

    /**
     * Call the "beforeXYZPreCache" or "afterXYZPreCache" callback (method if it exists)
     *
     * @param string $method             The hook method name to call.
     * @param array  $preCacheParameters The parameters that will be passed to the PreCache method that $method is a
     *                                   hook for.
     * @return boolean
     * @throws PreCacherException Thrown when the hook's parameters are incorrect in some way.
     */
    private function callPreCacheHook(string $method, array $preCacheParameters): bool
    {
        // call the "beforeXYZPreCache" method if it exists
        if (!method_exists(static::class, $method)) {
            return false;
        }

        // make sure it's return type is ok
        try {
            $reflectionMethod = (new ReflectionClass(static::class))->getMethod($method);
        } catch (ReflectionException $e) {
            // $e is thrown when the method is missing, but this was checked above
        }
        if (!in_array(Compatibility::reflectionMethodReturnType($reflectionMethod), ['void', null], true)) {
            throw PreCacherException::invalidPreCacheHookMethodReturnType($method, static::class);
        }

        // look for the parameters and populate them
        $hookParams = [];
        foreach ($reflectionMethod->getParameters() as $reflectionParameter) {

            // check the parameter's name matches an available Lookup
            $lookupClassName = ucFirst($reflectionParameter->getName());
            if (isset($this->resolvedLookupClasses[$lookupClassName])) {

                // true = the Lookup is being used
                // false = the Lookup is not being used
                $hookParams[] =
                    ((isset($preCacheParameters[$lookupClassName]))
                    && ($preCacheParameters[$lookupClassName] instanceof Lookup));
            } else {
                throw PreCacherException::invalidPreCacheHookMethodParamName(
                    $lookupClassName,
                    $method,
                    static::class
                );
            }

            // make sure the parameter is a boolean
            $reflectionParameterType = $reflectionParameter->getType(); // for phpstan
            $typeName = ($reflectionParameterType ? Compatibility::parameterType($reflectionParameterType) : null);
            if ($typeName != 'bool') {
                throw PreCacherException::invalidPreCacheHookMethodParamType(
                    $lookupClassName,
                    $typeName,
                    $method,
                    static::class
                );
            }
        }

        // call the hook method
        $callable = [$this, $method];
        if (is_callable($callable)) { // to please phpstan
            call_user_func_array($callable, $hookParams);
        }

        return true;
    }
}
