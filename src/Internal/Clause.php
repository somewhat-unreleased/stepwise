<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\ClauseException;
use CodeDistortion\Stepwise\Lookup;

/**
 * Represents a database clause, used when building a query
 */
abstract class Clause
{
    /**
     * The field name this clause is about
     *
     * @var string|null
     */
    protected $fieldName = null;

//    /**
//     * Return the field's name
//     *
//     * @return string
//     */
//     public function getFieldName(): string
//     {
//         return $this->fieldName;
//     }

    /**
     * Build the clause as a string
     *
     * @param array $tableObjects The possible TempTable or Lookup that might contain this field.
     * @param array $queryValues  The list of query values is built up in this array reference.
     * @return string|null
     */
//    abstract public function render(array $tableObjects, array &$queryValues): ?string; // @TODO PHP 7.1
    abstract public function render(array $tableObjects, array &$queryValues);



    /**
     * Pick the table alias that contains the given field from the given $tableObjects
     *
     * @param array   $tableObjects   The possible TempTable or Lookup that might contain this field.
     * @param string  $fieldName      The field to look for.
     * @param boolean $throwException Should an exception be thrown if the field isn't found?.
     * @return string|null
     */
//    protected function pickFieldTableAlias(array $tableObjects, string $fieldName, bool $throwException = true): ?string // @TODO PHP 7.1
    protected function pickFieldTableAlias(array $tableObjects, string $fieldName, bool $throwException = true)
    {
        // check if the field belongs to one of the given table objects
        foreach ($tableObjects as $tableAlias => $tableObject) {
            if (($this->isTableObj($tableObject))
            && ($tableObject->hasField($fieldName))) {
                return (is_string($tableAlias)
                    ? $tableAlias
                    : $tableObject->getTableName());
            }
        }
        // if a table is actually a string, assume the field belongs to it
        foreach (array_reverse($tableObjects) as $tableAlias => $tableObject) {
            if (is_string($tableObject)) {
                return (string) $tableAlias;
            }
        }
        // not found - throw an exception?
        if ($throwException) {
            throw ClauseException::fieldNotFound($fieldName, array_keys($tableObjects));
        }
        return null;
    }

    /**
     * Check whether the given variable is a "table" object
     *
     * @param string|Lookup|TempTable|null $tableObject The variable to check.
     *
     * @return boolean
     */
    private function isTableObj($tableObject): bool
    {
        return (($tableObject instanceof Lookup) || ($tableObject instanceof TempTable));
    }
}
