<?php

namespace CodeDistortion\Stepwise\Internal;

use DB;

/**
 * Represents a temporary table in the database
 *
 * Used by the Stepwise during the search process.
 */
class TempTable
{
    /**
     * The name of the temp-table in the database
     *
     * @var string
     */
    protected $name = null;

    /**
     * The primary key fields to use (if available) when creating the database table
     *
     * @var array
     */
    protected $primaryKeyFields = [];

    /**
     * The field database definitions
     *
     * @var array
     */
    protected $fieldDefinitions = [];

    /**
     * Whether the table has actually been created in the database yet
     *
     * @var boolean
     */
    protected $existsInDB = false;

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
     * Constructor
     *
     * @param string $name             The name of the temp-table in the database.
     * @param array  $primaryKeyFields The primary key fields to use (if available) when creating the database table.
     * @param array  $fieldDefinitions The field database definitions.
     */
    public function __construct(
        string $name,
        array $primaryKeyFields,
        array $fieldDefinitions
    ) {
        $this->name = $name;
        $this->primaryKeyFields = $primaryKeyFields;
        $this->fieldDefinitions = $fieldDefinitions;
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
     * Returns the table's database name
     *
     * @return string
     */
    public function getTableName(): string
    {
        return $this->name;
    }

    /**
     * Returns whether the table has actually been created in the database yet
     *
     * @return boolean
     */
    public function existsInDB(): bool
    {
        return $this->existsInDB;
    }

    /**
     * Let the caller set the existsInDB setting
     *
     * @param boolean $existsInDB The new setting.
     * @return self
     */
    public function setExistsInDB(bool $existsInDB): self
    {
        $this->existsInDB = $existsInDB;

        return $this; // chainable
    }

    /**
     * Lets the caller specify a new field to track
     *
     * @param string $name       The field's name.
     * @param string $definition The database definition for this field.
     * @return self
     */
    public function trackField(string $name, string $definition): self
    {
        $this->fieldDefinitions[$name] = $definition;

        return $this; // chainable
    }

    /**
     * Check whether the given field has been defined
     *
     * @param string $name The field-name to check.
     * @return boolean
     */
    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->fieldDefinitions);
    }

    /**
     * Return the full list of defined fields
     *
     * @return array
     */
    public function getFieldNames(): array
    {
        return (array) array_combine(
            array_keys($this->fieldDefinitions),
            array_keys($this->fieldDefinitions)
        );
    }

//     /**
//      * Return the field definition for the given field (if present)
//      *
//      * @param string $field The field to get the definition for.
//      * @return string|null
//      */
//     public function getFieldDefinition(string $field): ?string
//     {
//         return ($this->fieldDefinitions[$field] ?? null);
//     }

    /**
     * Create the corresponding table in the database
     *
     * @return self
     */
    public function createTempTable(): self
    {
        // build the list of fields to create
        $fieldDefns = [];
        foreach ($this->fieldDefinitions as $fieldName => $fieldDefinition) {
            $isPrimaryKeyField = (in_array($fieldName, $this->primaryKeyFields));
            $fieldDefns[] = "`".$fieldName."` ".$this->treatFieldDefinition($fieldDefinition, !$isPrimaryKeyField);
        }

        // build the primary key to use
        $primaryKeyFields = [];
        foreach ($this->primaryKeyFields as $fieldName) {
            if (isset($this->fieldDefinitions[$fieldName])) {
                $primaryKeyFields[] = $fieldName;
            }
        }
        $indexes = [];
        if (count($primaryKeyFields)) {
            $indexes[] = "PRIMARY KEY `primary` (`".implode("`, `", $primaryKeyFields)."`)";
        }

        // build the query
        $query = "CREATE TEMPORARY TABLE `".$this->name."` "
                ."(\n".implode(",\n", array_merge($fieldDefns, $indexes))."\n) "
                ."ENGINE = MEMORY";

        // run the query
        if ($this->runQueries) {
            DB::statement($query);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query); // used by testing code
        }

        $this->existsInDB = true;

        return $this; // chainable
    }

    /**
     * Drop the corresponding table in the database
     *
     * @return self
     */
    public function dropTempTable(): self
    {
        if ($this->existsInDB) {

            $query = "DROP TEMPORARY TABLE `".$this->name."`";

            if ($this->runQueries) {
                DB::statement($query);
            }
            if ($this->queryTracker) {
                $this->queryTracker->trackQuery($query); // used by testing code
            }

            $this->existsInDB = false;
        }

        return $this; // chainable
    }

    /**
     * Take the given database field definition and add - or remove the NULL requirement
     *
     * @param string  $fieldDefinition The database field definition.
     * @param boolean $forceNull       Whether NULL should be added or removed.
     * @return string|null
     */
//    protected static function treatFieldDefinition(string $fieldDefinition, bool $forceNull = false): ?string // @TODO PHP 7.1
    protected static function treatFieldDefinition(string $fieldDefinition, bool $forceNull = false)
    {
        # turn NOT NULL fields into NULL fields if requested (used for the step-wise temporary tables)
        if ($forceNull) {
            return preg_replace('/\bNOT NULL\b/i', 'NULL', $fieldDefinition);
        } else {
            return preg_replace('/\bNULL DEFAULT NULL\b/i', 'NOT NULL', $fieldDefinition);
        }
    }
}
