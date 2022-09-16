<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\SearchException;
use ReflectionClass;
use ReflectionException;

trait StepwiseInfoForOthers
{
    /**
     * Return the LookupTables that are used by this class
     *
     * Used by the PreCacher class.
     * @return array
     */
    public static function getLookupClasses(): array
    {
        try {
            return (new ReflectionClass(static::class))->newInstanceWithoutConstructor()->lookupClasses;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    /**
     * Return the field definitions held in this class
     *
     * Used by the Lookup class.
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        try {
            return (new ReflectionClass(static::class))->newInstanceWithoutConstructor()->fieldDefinitions;
        } catch (ReflectionException $e) {
            return [];
        }
    }

    /**
     * Return whether the given field exists in this Stepwise
     *
     * Used by the Results class.
     * @param string $name The name of the field to check.
     * @return boolean
     */
    public function fieldDefinitionExists(string $name): bool
    {
        return (isset($this->resolvedFieldDefinitions[$name]));
    }

    /**
     * Return the definition of the given order-by alias
     *
     * Used by the Results class.
     * @param string $name The order-by alias to get.
     * @return array
     */
    public function getOrderByAlias(string $name): array
    {
        if (!isset($this->orderByAliases[$name])) {
            throw SearchException::invalidOrderBy($name);
        }
        return $this->orderByAliases[$name];
    }

    /**
     * Return the tags that have been created
     *
     * Used by testing code.
     * @return array
     */
    public function getFilterRefTags(): array
    {
        $this->blockIfNotTesting(__FUNCTION__);

        return $this->filterRefTags;
    }


    /**
     * Return the name of the temp-table used by the given tag
     *
     * Used by testing code.
     * @param string|null $tagName The tag to get the temp-table name for.
     * @return string|null
     */
//    public function getFilterRefTagTempTableName(string $tagName = null): ?string // @TODO PHP 7.1
    public function getFilterRefTagTempTableName(string $tagName = null)
    {
        $this->blockIfNotTesting(__FUNCTION__);

        $filterRef = $this->getTaggedFilterRef($tagName);
        if ($filterRef->curTempTable()) {
            return $filterRef->curTempTable()->getTableName();
        }
    }
}
