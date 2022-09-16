<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Exceptions\LookupException;
use CodeDistortion\Stepwise\Exceptions\SettingsException;
use CodeDistortion\Stepwise\Internal\QueryTracker;
use DB;
use ReflectionClass;
use ReflectionException;

/**
 * This class defines a "lookup-table" table that stores a slice of information used when performing a search
 *
 * This class knows how to store new data in its table, and remove unused data.
 * The PreCacher uses instructs LookupTables to create their corresponding database table, and also controls
 * which data to store.
 * The Stepwise uses LookupTables when working out which tables to use when searching.
 */
abstract class Lookup
{
    /**
     * The Stepwise class this Lookup belongs to
     *
     * @var string|null
     */
    protected $stepwiseClass = null;

    /**
     * The database table name this class will use
     *
     * Add '%stubs%' that will be replaced later
     * @var string
     */
    protected $tableName = '';

    /**
     * The list of fields that the db table contains
     *
     * @var array
     */
    protected $fields = [];

    /**
     * The primary-key fields the db table uses
     *
     * @var array
     */
    protected $primaryKey = [];

    /**
     * Arrays of unique-indexes the db table will have
     *
     * eg. [['product_id', 'product_allergen_id'], ['maker_id']].
     * or named: ['prod_all' => ['product_id', 'product_allergen_id'], 'maker_id_baby' => ['maker_id']].
     * @var array
     */
    protected $unique = [];

    /**
     * Arrays of regular indexes the db table will have
     *
     * eg. [['product_id', 'product_allergen_id'], ['maker_id']].
     * or named: ['prod_all' => ['product_id', 'product_allergen_id'], 'maker_id_yeah' => ['maker_id']].
     * @var array
     */
    protected $indexes = [];

    /**
     * Arrays of fulltext-indexes the db table will have
     *
     * eg. [['product_name', 'product_description'], ['product_name']].
     * or named: ['prod_name_desc' => ['product_name', 'product_description'], 'prod_name' => ['product_name']].
     * @var array
     */
    protected $fulltext = [];

    /**
     * Definitions of the db fields
     *
     * May be empty - as field definitions can be stored centrally in the Stepwise class
     * Keys are the field names and the values are their definitions.
     * [
     *     'product_id' => "BIGINT(20) UNSIGNED NOT NULL",
     *     'product_price' => "DECIMAL(18, 3) UNSIGNED NOT NULL",
     * ]
     * @var array
     */
    protected $fieldDefinitions = [];

    /**
     * Fields in the db table to ignore when checking for changes
     *
     * eg. a POINT field's binary value can't be compared to the original "POINT(:long, :lat)" that created it, and
     * it's probably ok to skip these
     * @var array
     */
    protected $skipFieldsWhenLookingForChanges = [];

    /**
     * The largest the new-row-cache internal can grow to before being sent to the database
     *
     * @var integer
     */
    protected $newRowCacheMaxSize = 25;

    /**
     * The db engine the primary table will use
     *
     * @var string
     */
    protected $primaryTableEngine = 'InnoDB';

    /**
     * The db engine the comparison table will use
     *
     * @var string
     */
    protected $comparisonTableEngine = 'MEMORY';

    /**
     * The charset the primary and comparison tables will use
     *
     * @var string
     */
    protected $charset = 'utf8mb4';

    /**
     * The collation the primary and comparison tables will use
     *
     * @var string
     */
    protected $collation = 'utf8mb4_unicode_ci';



    /**
     * The primary db table name this object represents - with stub replacements made
     *
     * @var string|null
     */
    private $primaryTableName = null;

    /**
     * The randomly generated comparison table name
     *
     * @var string|null
     */
    private $comparisonTableName = null;

    /**
     * The replacements used in the primary-table name
     * @var array
     */
    private $stubReplacements = [];

    /**
     * Internal cache of rows to eventually flush out to the database
     *
     * @var array
     */
    private $newRowCache = [];



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
     * Should checking be applied to report data thrashing?
     *
     * @var boolean
     */
    private $thrashTestMode = false;





    /**
     * Constructor
     *
     * @param array   $stubReplacements Replacement strings that will be used when generating the table-name.
     * @param boolean $thrashTestMode   When enabled, checking is applied when pre-caching to detect data
     *                                  thrashing.
     * @throws SettingsException Thrown when something is wrong with the fields/primary-key/indexes.
     */
    public function __construct(array $stubReplacements = [], bool $thrashTestMode = false)
    {
        $this->stubReplacements = $stubReplacements;

        // allow for the field definitions to be stored in this class, or in the Stepwise
        // the ones in this class take precedence (and the Stepwise might not exist)
        $this->fieldDefinitions = array_merge(
            ($this->stepwiseClass ? $this->stepwiseClass::getFieldDefinitions() : []),
            $this->fieldDefinitions
        );

        // check that the fields are all ok!
        $this->checkFields();

        // set the desired thrash-test mode
        $this->setThrashTestMode($thrashTestMode);
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
     * Should checking be applied to report data thrashing?
     *
     * @param boolean $thrashTestMode Turn the setting on or off.
     * @return self
     */
    private function setThrashTestMode(bool $thrashTestMode): self
    {
        $this->thrashTestMode = $thrashTestMode;

        return $this; // chainable
    }



    /**
     * Builds the database table name
     *
     * (it substitutes in the given replacements).
     * @return string
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    private function primaryTableName(): string
    {
        if (is_null($this->primaryTableName)) {

            $keys = array_map(
                function ($field) {
                    return '%'.$field.'%';
                },
                array_keys($this->stubReplacements)
            );
            $return = str_replace($keys, $this->stubReplacements, $this->tableName);

            if (mb_strpos($return, '%') !== false) {
                throw LookupException::unresolvedStubs($return);
            }

            $this->primaryTableName = $return; // for phpstan
        }
        return $this->primaryTableName;
    }

    /**
     * Builds the database table name
     *
     * @return string
     */
    private function comparisonTableName(): string
    {
        if (is_null($this->comparisonTableName)) {

            if ($this->testMode) {
                $this->comparisonTableName = '_lookup_comparison_1';
            } else {
                $this->comparisonTableName = '_lookup_comparison_'.md5(uniqid((string) mt_rand(), true));
            }

        }
        return $this->comparisonTableName;
    }

    /**
     * Returns the table-name this object represents
     *
     * @return string
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    public function getTableName(): string
    {
        return $this->primaryTableName();
    }

    /**
     * Check if this table contains the given field
     *
     * @param string $field The field to check.
     * @return boolean
     */
    public function hasField(string $field): bool
    {
        return in_array($field, $this->fields);
    }

    /**
     * Return the list of fields this table contains
     *
     * @return array
     */
    public function getFieldNames(): array
    {
        return $this->fields;
    }

    /**
     * Return the field definition for the given field (if present)
     *
     * Used by the Stepwise class.
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
     * Create the primary look-up table in the database
     *
     * @return self
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     * @throws LookupException Thrown when the table can't be created.
     */
    public function createPrimaryTable(): self
    {
        $query1 = "DROP TABLE IF EXISTS `".$this->primaryTableName()."`";
        $query2 = $this->createTableQuery(
            $this->primaryTableName(),
            $this->primaryTableEngine,
            $this->charset,
            $this->collation,
            false, // $isTemporary
            false // primaryKeyFieldsOnly
        );
        if ($this->runQueries) {
            DB::statement($query1);
            DB::statement($query2);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query1);
            $this->queryTracker->trackQuery($query2);
        }

        return $this; // chainable
    }

    /**
     * Create a comparison look-up table in the database
     *
     * @return self
     * @throws LookupException Thrown when the table can't be created.
     */
    private function createComparisonTable(): self
    {
        $query = $this->createTableQuery(
            $this->comparisonTableName(),
            $this->comparisonTableEngine,
            $this->charset,
            $this->collation,
            true, // $isTemporary
            true // primaryKeyFieldsOnly
        );
        if ($this->runQueries) {
            DB::statement($query);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query);
        }

        return $this; // chainable
    }





    /**
     * Start the update process
     *
     * Creates the temporary comparison table
     * @return self
     * @throws LookupException Thrown when the table can't be created.
     */
    public function startPopulateProcess(): self
    {
        $this->createComparisonTable();

        return $this; // chainable
    }

    /**
     * End the update process
     *
     * Deletes left over rows that shouldn't exist any more.
     * Removes the temporary comparison table.
     * @return self
     * @throws LookupException Thrown when the query can't be built.
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    public function finishPopulateProcess(): self
    {
        $this->processOutstandingRows(true);

        // remove the left over primary keys from the primary table
        // ie. remove any entries from the primary table where a copy still exists in the temp table
        $query1 = $this->deleteLeftOversFromPrimaryTableQuery();

        // drop the comparison temp table
        $query2 = "DROP TEMPORARY TABLE IF EXISTS `".$this->comparisonTableName()."`";

        if ($this->runQueries) {
            DB::delete($query1);
            DB::statement($query2);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query1);
            $this->queryTracker->trackQuery($query2);
        }

        return $this; // chainable
    }

    /**
     * Populate the comparison table with ALL existing rows that will be updated
     *
     * @return self
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    public function willUpdateEverything()
    {
        return $this->willUpdate([]);
    }

    /**
     * Populate the comparison table with SPECIFIC existing rows that will be updated
     *
     * @param array $selectValues Field values to use when picking rows to update.
     * @return self
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    public function willUpdate(array $selectValues): self
    {
        $qv = $this->populateComparisonTableQuery($selectValues);
        if ($this->runQueries) {
            DB::statement($qv['query'], $qv['values']);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($qv['query'], $qv['values']);
        }

        return $this; // chainable
    }

    /**
     * Add a row of values to the database
     *
     * To minimise queries, the row is temporarily stored in memory and then processed in a chunk.
     * @param array $row The row of values to add.
     * @return self
     * @throws LookupException Thrown when some data is missing or extraneous data is given.
     */
    public function addRow(array $row): self
    {
        return $this->addRows([$row]);
    }

    /**
     * Add rows of values to the database
     *
     * To minimise queries, the row s are temporarily stored in memory and then processed in a chunk.
     * @param array $rows Rows of values to add.
     * @return self
     * @throws LookupException Thrown when some data is missing or extraneous data is given.
     */
    public function addRows(array $rows): self
    {
        // check that each row contains the required fields
        foreach ($rows as $index => $row) {

            // check that all the required fields are there
            $rowFields = (array) array_combine(array_keys($row), array_keys($row));
            $missingFields = [];
            foreach ($this->fields as $field) {
                if (!array_key_exists($field, $rowFields)) {
                    $missingFields[] = $field;
                }
                unset($rowFields[$field]);
            }
            if (count($missingFields)) {
                throw LookupException::missingFields($missingFields, $this->fields, static::class);
            }
            // check there are no unexpected fields
            if (count($rowFields)) {
                throw LookupException::unexpectedFields($rowFields, $this->fields, static::class);
            }

            // cast any objects to strings (eg. Carbon objects) so they can be directly compared to the existing
            // database values.
            foreach ($row as $field => $value) {
                if (is_object($value)) {
                    $rows[$index][$field] = (string) $value;
                }
            }
        }

        // add these new rows in the new-row-cache
        $this->newRowCache = array_merge($this->newRowCache, array_values($rows));

        // process these new rows if enough have built up so far
        if (count($this->newRowCache) >= $this->newRowCacheMaxSize) {
            $this->processOutstandingRows();
        }

        return $this; // chainable
    }

    /**
     * Process the rows of values that have been temporarily stored in memory
     *
     * Save them in the database
     * @param boolean $finish When true, all the outstanding rows will be processed (instead of just enough to bring
     *                        the amount left below the $newRowCacheMaxSize threshold).
     * @return self
     * @throws LookupException Thrown when the query can't be built.
     */
    private function processOutstandingRows(bool $finish = false): self
    {
        // process enough rows in blocks of newRowCacheMaxSize to take it below newRowCacheMaxSize
        // or if finishing - process all the outstanding rows
        while ((($finish) && (count($this->newRowCache)))
        || ((!$finish) && (count($this->newRowCache) >= $this->newRowCacheMaxSize))) {

            // take a chunk of rows to look at
            $newRows = array_splice($this->newRowCache, 0, $this->newRowCacheMaxSize);

            // load the rows if they exist already
            $existingRows = [];
            $qv = $this->findPrimaryTableValuesQuery($newRows);
            if ($this->runQueries) {
                $existingRows = DB::select($qv['query'], $qv['values']);
            }
            if ($this->queryTracker) {
                $this->queryTracker->trackQuery($qv['query'], $qv['values']);
            }

            // work out which rows to update and which to remove
            list($unchangedRows, $rowsToAdd, $rowsToUpdate) = array_values(
                $this->compareRowDifferences($newRows, $existingRows)
            );

            // don't do anything for rows with no change
            $usedPrimaryKeys = $this->getPrimaryKeyValues($unchangedRows);

            // add new rows that don't exist yet
            if (count($rowsToAdd)) {

                $qv = $this->populatePrimaryTableQuery($rowsToAdd);
                if ($this->runQueries) {
                    DB::insert($qv['query'], $qv['values']);
                }
                if ($this->queryTracker) {
                    $this->queryTracker->trackQuery($qv['query'], $qv['values']);
                }
                $usedPrimaryKeys = array_merge($usedPrimaryKeys, $this->getPrimaryKeyValues($rowsToAdd));
            }

            // update existing rows that have changes
            if (count($rowsToUpdate)) {

                // update queries need to be run one at a time
                foreach ($rowsToUpdate as $row) {

                    $qv = $this->updatePrimaryTableValuesQuery($row);
                    if ($this->runQueries) {
                        DB::update($qv['query'], $qv['values']);
                    }
                    if ($this->queryTracker) {
                        $this->queryTracker->trackQuery($qv['query'], $qv['values']);
                    }
                }
                $usedPrimaryKeys = array_merge($usedPrimaryKeys, $this->getPrimaryKeyValues($rowsToUpdate));
            }

            // remove the used primary keys from the comparison temp table (so left overs can be identified later)
            if (count($usedPrimaryKeys)) {

                $qv = $this->deleteFromComparisonTableQuery($usedPrimaryKeys);
                if ($this->runQueries) {
                    DB::delete($qv['query'], $qv['values']);
                }
                if ($this->queryTracker) {
                    $this->queryTracker->trackQuery($qv['query'], $qv['values']);
                }
            }
        }

        return $this; // chainable
    }

    /**
     * Remove rows from the temp table - as if they've been processed
     *
     * Done so they're not deleted from the real table when finished.
     * @param array $primaryKeyValues The fields to ignore.
     * @return self
     * @throws LookupException Thrown when invalid primary-key values are given.
     */
    public function ignoreRows(array $primaryKeyValues): self
    {
        if (!count($primaryKeyValues)) {
            throw LookupException::noPrimaryKey(static::class);
        }

        foreach (array_keys($primaryKeyValues) as $fieldName) {
            if (!in_array($fieldName, $this->primaryKey)) {
                throw LookupException::fieldNotPrimary($fieldName, static::class);
            }
        }

        $qv = static::buildQueryParts(array_keys($primaryKeyValues), [$primaryKeyValues]);
        $qv['query'] = "DELETE FROM `".$this->comparisonTableName()."` "."\n"
                        ."WHERE ".$qv['query'];
        if ($this->runQueries) {
            DB::delete($qv['query'], $qv['values']);
        }
        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($qv['query'], $qv['values']);
        }

        return $this; // chainable
    }


    /**
     * Build some parts used when creating the tables this class uses
     *
     * @param string  $tableName            The name of the table to create.
     * @param string  $engine               The engine type the new table will use (innodb, memory etc).
     * @param string  $charset              The charset the new table will use.
     * @param string  $collation            The collation the new table will use.
     * @param boolean $isTemporary          Will the table be a temporary table?.
     * @param boolean $primaryKeyFieldsOnly Should primary key fields only be used?.
     * @return string
     * @throws LookupException Thrown when the table can't be created.
     */
    private function createTableQuery(
        string $tableName,
        string $engine,
        string $charset,
        string $collation,
        bool $isTemporary,
        bool $primaryKeyFieldsOnly = false
    ): string {

        // build the relevant fields to create
        $fieldDefns = [];
        foreach ($this->getFieldNames() as $fieldName) {
            if ((!$primaryKeyFieldsOnly) || (in_array($fieldName, $this->primaryKey))) {
                if (isset($this->fieldDefinitions[$fieldName])) {
                    $fieldDefns[] = "`".$fieldName."` ".$this->fieldDefinitions[$fieldName];
                } else {
                    throw LookupException::fieldNotDefined($fieldName, static::class);
                }
            }
        }

        // pick the indexes to create
        $indexes = [];
        // add the primary-key
        $indexes[] = "PRIMARY KEY `primary` (`".implode("`, `", $this->primaryKey)."`)";
        // add the regular indexes
        if (!$primaryKeyFieldsOnly) {

            $indexTypes = [
                'UNIQUE KEY' => 'unique',
                'KEY' => 'indexes',
                'FULLTEXT KEY' => 'fulltext',
            ];

            foreach ($indexTypes as $indexTypeKey => $indexType) {
                foreach ($this->$indexType as $indexName => $indexFields) {
                    $indexes[] = $indexTypeKey." "
                                ."`".(!is_int($indexName) ? $indexName : implode("_", $indexFields))."` "
                                ."(`".implode("`, `", $indexFields)."`)";
                }
            }
        }

        // if everything's in order
        if ((count($fieldDefns)) && (count($this->primaryKey))) {

            return "CREATE ".($isTemporary ? "TEMPORARY " : "")."TABLE IF NOT EXISTS `".$tableName."` (\n"
                .implode(",\n", array_merge($fieldDefns, $indexes))."\n"
            .") ENGINE=".$engine." DEFAULT CHARSET=".$charset." COLLATE=".$collation;
        }
        throw LookupException::couldNotCreateTable($tableName, $isTemporary, static::class);
    }

    /**
     * Build the query to populate the comparison table with existing rows that will be updated afterwards
     *
     * @param array $selectValues Field values to use when picking rows to update.
     * @return array
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    private function populateComparisonTableQuery(array $selectValues = []): array
    {
        $clauseFields = $values = [];
        foreach ($selectValues as $fieldName => $clauseValues) {

            // change the clause back to a single value if there's only one in an array
            if ((is_array($clauseValues)) && (count($clauseValues) == 1)) {
                $clauseValues = reset($clauseValues);
            }

            // create a "WHERE x IN (...)" clause
            if (is_array($clauseValues)) {
                $aliases = [];
                foreach ($clauseValues as $value) {
                    $aliases[] = "?";
                    $values[] = $value;
                }
                $clauseFields[] = "`".$fieldName."` IN (".implode(", ", $aliases).")";
            // create a "WHERE x = y" clause
            } else {
                $clauseFields[] = "`".$fieldName."` = ?";
                $values[] = $clauseValues;
            }
        }

        $query = "INSERT IGNORE INTO `".$this->comparisonTableName()."` "
                ."(`".implode("`, `", $this->primaryKey)."`)\n"
                ."SELECT `".implode("`, `", $this->primaryKey)."`\n"
                ."FROM `".$this->primaryTableName()."`\n"
                .(count($clauseFields) ? "WHERE ".implode("\nAND ", $clauseFields) : "");

        return [
            // uses INSERT IGNORE so duplicate primary keys are dropped
            'query' => rtrim($query),
            'values' => $values
        ];
    }

    /**
     * Load rows from the primary table
     *
     * @param array $rows The rows of values to try and find.
     * @return array
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    private function findPrimaryTableValuesQuery(array $rows): array
    {
        $return = $this->buildQueryParts($this->primaryKey, $rows);
        $return['query'] = "SELECT `".implode("`, `", $this->fields)."`\n"
                            ."FROM `".$this->primaryTableName()."`\n"
                            ."WHERE ".$return['query'];
        return $return;
    }

    /**
     * Build the query parts for a select or delete query
     *
     * @param array $fieldNames The fields to search for.
     * @param array $rows       The rows of values to pick out $fieldNames of for the query.
     * @return array
     */
    private function buildQueryParts(array $fieldNames, array $rows): array
    {

        $fieldNames = array_unique($fieldNames);
        $fieldNames = (array) array_combine($fieldNames, $fieldNames);

        // out of the $fieldNames to look for, work out which is the most common
        $uniqueFieldValues = [];
        foreach ($rows as $index => $row) {
            foreach ($fieldNames as $fieldName) {
                $uniqueFieldValues[$fieldName][(string) $row[$fieldName]][$index] = $index;
            }
        }

        $uniqueFieldValueFreq = [];
        foreach (array_keys($uniqueFieldValues) as $fieldName) {
            $uniqueFieldValueFreq[$fieldName] = count($uniqueFieldValues[$fieldName]);
        }
        asort($uniqueFieldValueFreq); // put the field names with the lowest frequency first
        $fieldOrder = array_keys($uniqueFieldValueFreq);
        $firstFieldName = reset($fieldOrder);

        $otherFieldNames = $fieldNames;
        unset($otherFieldNames[$firstFieldName]);



        // for the most common fieldName, build a query-part out of it, and add the rest on afterwards
        $queryParts = $queryValues = [];
        // if there's more to add
        if (count($otherFieldNames)) {
            foreach ($uniqueFieldValues[$firstFieldName] as $indexes) {

                $headIndex = reset($indexes);
                $fieldValue = $rows[$headIndex][$firstFieldName];

                $rowSubset = [];
                foreach (array_intersect(array_keys($rows), array_keys($indexes)) as $index) {
                    $rowSubset[] = $rows[$index];
                }

                // recurse, get the query part for the rest of the rows
                $rest = $this->buildQueryParts($otherFieldNames, $rowSubset);

                // add the rest of the query to the current field value
                $queryParts[] = '`'.$firstFieldName.'` = ? AND ('.$rest['query'].')';
                $queryValues[] = $fieldValue;
                $queryValues = array_merge($queryValues, $rest['values']);
            }
        // or if this is the last fieldName to process
        } else {
            $valuePlaceholders = [];
            foreach ($uniqueFieldValues[$firstFieldName] as $indexes) {

                $headIndex = reset($indexes);
                $fieldValue = $rows[$headIndex][$firstFieldName];

                $valuePlaceholders[] = '?';
                $queryValues[] = $fieldValue;
            }
            $queryParts[] = (count($valuePlaceholders) == 1
                ? '`'.$firstFieldName.'` = '.array_pop($valuePlaceholders)
                : '`'.$firstFieldName.'` IN ('.implode(',', $valuePlaceholders).')'
            );
        }

        return [
            'query' => "(".implode(") OR (", $queryParts).")",
            'values' => $queryValues,
        ];
    }





    /**
     * Work out which rows need updating / adding / deleting
     *
     * @param array $newRows      New rows that are proposed to be saved or updated if they don't exist already.
     * @param array $existingRows The equivalent rows that exist already, for comparing to.
     * @return array
     */
    private function compareRowDifferences(array $newRows, array $existingRows): array
    {
        $existingRows = $this->combineRowData($existingRows);
        $newRows = $this->combineRowData($newRows);

        $unchangedRows = $rowsToAdd = $rowsToUpdate = [];
        $compareRowDifferences = function (
            $newRows,
            $existingRows,
            $depth = 0
        ) use (
            &$compareRowDifferences,
            &$unchangedRows,
            &$rowsToAdd,
            &$rowsToUpdate
        ) {

            // if we've navigated through the primary keys, the $newRows and $existingRows variables will contain
            // the rows themselves that need comparing
            if ($depth == count($this->primaryKey)) {
                if (!is_null($existingRows)) {

                    // remove the fields that need to be skipped
                    $tempExistingRows = $existingRows;
                    $tempNewRows = $newRows;
                    foreach ($this->skipFieldsWhenLookingForChanges as $fieldName) {
                        unset($tempExistingRows[$fieldName], $tempNewRows[$fieldName]);
                    }

                    // mysql returns decimal fields as strings, which breaks the comparison when comparing the existing
                    // rows to the proposed rows. detect this an convert the values to floats or (integers depending on
                    // the new data) before comparison
                    foreach ($tempNewRows as $fieldName => $value) {
                        // check if the database value needs to be changed to a float
                        if ((is_float($value))
                        && (array_key_exists($fieldName, $tempExistingRows))
                        && (is_string($tempExistingRows[$fieldName]))) {
                            $tempExistingRows[$fieldName] = (float) $tempExistingRows[$fieldName];
                        // check if the database value needs to be changed to an integer
                        } elseif ((is_int($value))
                        && (array_key_exists($fieldName, $tempExistingRows))
                        && (is_string($tempExistingRows[$fieldName]))) {
                            $tempExistingRows[$fieldName] = (int) $tempExistingRows[$fieldName];
                        }
                    }

                    // check if any part of this row has changed
                    if (serialize($tempExistingRows) === serialize($tempNewRows)) {
                        $unchangedRows[] = $newRows;
                    } else {
                        // if thrash-test mode is on, display the differences and quit
                        if ($this->thrashTestMode) {

                            print PHP_EOL;
                            print 'This new row is different to the existing row in '.static::class.':'.PHP_EOL.PHP_EOL;
                            print 'EXISTING ROW DATA:'.PHP_EOL;
                            print serialize($tempExistingRows).PHP_EOL.PHP_EOL;
                            var_dump($tempExistingRows);
                            print PHP_EOL;
                            print 'NEW ROW DATA:'.PHP_EOL;
                            print serialize($tempNewRows).PHP_EOL.PHP_EOL;
                            var_dump($tempNewRows);
                            exit;

                        }
                        $rowsToUpdate[] = $newRows;
                    }
                } else {
                    $rowsToAdd[] = $newRows;
                }
            // keep recursing until all the primary keys have been checked
            } else {
                foreach ($newRows as $primaryKeyValue => $proposedRow) {
                    $compareRowDifferences(
                        $newRows[$primaryKeyValue],
                        (isset($existingRows[$primaryKeyValue])? $existingRows[$primaryKeyValue] : null),
                        $depth + 1
                    );
                }
            }
        };
        $compareRowDifferences($newRows, $existingRows);

        return [
            'unchangedRows' => $unchangedRows,
            'rowsToAdd' => $rowsToAdd,
            'rowsToUpdate' => $rowsToUpdate,
        ];
    }

    /**
     * Re-structure the given rows of data into a hierarchy based on their primary-key values
     *
     * @param array $rows Rows of data.
     * @return array
     */
    private function combineRowData(array $rows): array
    {
        $combinedRows = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            ksort($row); // make sure the arrays' fields are compared in the same order later
            $blah =& $combinedRows;
            foreach ($this->primaryKey as $fieldName) {
                $primaryKeyValue = $row[$fieldName];
                if (isset($blah[$primaryKeyValue])) {
                    $blah =& $blah[$primaryKeyValue];
                } else {
                    $blah[$primaryKeyValue] = null;
                    $blah =& $blah[$primaryKeyValue];
                }
            }
            $blah = $row;
            unset($blah);
        }
        return $combinedRows;
    }

    /**
     * From the given sets of rows, pick out the primary key values
     *
     * @param array $rows Rows of data.
     * @return array
     */
    private function getPrimaryKeyValues(array $rows): array
    {
        $primaryKeyValues = [];
        foreach ($rows as $row) {
            $usedPrimaryKeyRow = [];
            foreach ($this->primaryKey as $fieldName) {
                $usedPrimaryKeyRow[$fieldName] = $row[$fieldName];
            }
            $primaryKeyValues[] = $usedPrimaryKeyRow;
        }
        return $primaryKeyValues;
    }


    /**
     * Build a query to populate the primary table with the given rows
     *
     * @param array $rows The rows to insert.
     *
     * @return array
     * @throws LookupException Thrown when the query can't be built.
     *
     */
    private function populatePrimaryTableQuery(array $rows): array
    {
        $queryParts = $values = [];
        $rowCount = 0;
        foreach ($rows as $row) {
            $aliases = [];
            $fieldCount = 0;
            foreach ($this->fields as $fieldName) {

                if (array_key_exists($fieldName, $row)) {

                    // see if a function and parameters were passed here (represented by an array)
                    // instead of simply a value
                    if (is_array($row[$fieldName])) {

                        // just a statement (no parameters, eg. NOW())
                        if (count($row[$fieldName]) == 1) {
                            $aliases[] = array_shift($row[$fieldName]);
                        // a statement (with parameters, probably something like POINT(:long, :lat))
                        } elseif (count($row[$fieldName]) == 2) {

                            $tempStatement = array_shift($row[$fieldName]);
                            $tempParameters = array_shift($row[$fieldName]);
                            if (is_array($tempParameters)) {

                                // resolve the parameters
                                $parts = (array) preg_split(
                                    '/(:[a-zA-Z0-9]+)/',
                                    $tempStatement,
                                    null,
                                    PREG_SPLIT_DELIM_CAPTURE
                                );
                                $statementParamCount = 0;
                                foreach ($parts as $index => $part) {

                                    $part = (string) $part; // for phpstan

                                    if (mb_substr($part, 0, 1) == ':') {
                                        $paramName = mb_substr($part, 1);
                                        if (!isset($tempParameters[$paramName])) {
                                            throw LookupException::populateStatementAndParamMismatch(static::class);
                                        }

                                        $alias = $this->convertIntToAlpha($fieldCount).$rowCount;
                                        $values[$alias] = $tempParameters[$paramName];
                                        $fieldCount++;

                                        $parts[$index] = ':'.$alias;
                                        $statementParamCount++;
                                    }
                                }
                                if ($statementParamCount != count($tempParameters)) {
                                    throw LookupException::populateStatementAndParamMismatch(static::class);
                                }
                                $aliases[] = implode($parts);
                            } else {
                                throw LookupException::populateParametersInvalid(static::class);
                            }
                        } else {
                            throw LookupException::populateParametersInvalid(static::class);
                        }
                    // just a plain value
                    } else {
                        $alias = $this->convertIntToAlpha($fieldCount).$rowCount;
                        $aliases[] = ':'.$alias;
                        $values[$alias] = $row[$fieldName];
                        $fieldCount++;
                    }
                } else {
                    throw LookupException::missingFieldValue($fieldName, static::class);
                }
            }
            $queryParts[] = "(".implode(", ", $aliases).")";
            $rowCount++;
        }
        return [
            'query' => "INSERT IGNORE INTO `".$this->primaryTableName()."` (`".implode("`, `", $this->fields)."`) "."\n"
                        ."VALUES ".implode(", ", $queryParts), // use "insert ignore" so duplicate primary keys are
                                                                     // dropped
            'values' => $values
        ];
    }

    /**
     * Build a query to update the primary table with the given row
     *
     * @param array $row The row to update.
     * @return array
     * @throws LookupException Thrown when the query can't be built.
     */
    private function updatePrimaryTableValuesQuery(array $row): array
    {
        $updateFields = $clauseFields = $values = [];
        $fieldCount = 0;
        foreach ($this->fields as $fieldName) {

            // see if a function and parameters were passed here instead of simply a value
            if (is_array($row[$fieldName])) {

                // just a statement (no parameters, eg. NOW())
                if (count($row[$fieldName]) == 1) {
                    if (in_array($fieldName, $this->primaryKey)) {
                        $clauseFields[] = "`".$fieldName."` = ".array_shift($row[$fieldName]);
                    } else {
                        $updateFields[] = "`".$fieldName."` = ".array_shift($row[$fieldName]);
                    }
                // a statement (with parameters, probably something like POINT(:long, :lat))
                } elseif (count($row[$fieldName]) == 2) {

                    $tempStatement = array_shift($row[$fieldName]);
                    $tempParameters = array_shift($row[$fieldName]);
                    if (is_array($tempParameters)) {

                        // resolve the parameters
                        $parts = (array) preg_split(
                            '/(:[a-zA-Z0-9]+)/',
                            $tempStatement,
                            null,
                            PREG_SPLIT_DELIM_CAPTURE
                        );
                        $statementParamCount = 0;
                        foreach ($parts as $index => $part) {

                            $part = (string) $part; // for phpstan

                            if (mb_substr($part, 0, 1) == ':') {
                                $paramName = mb_substr($part, 1);
                                if (!isset($tempParameters[$paramName])) {
                                    throw LookupException::populateStatementAndParamMismatch(static::class);
                                }

                                $alias = $this->convertIntToAlpha($fieldCount++);
                                $values[$alias] = $tempParameters[$paramName];

                                $parts[$index] = ':'.$alias;
                                $statementParamCount++;
                            }
                        }
                        if ($statementParamCount != count($tempParameters)) {
                            throw LookupException::populateStatementAndParamMismatch(static::class);
                        }

                        if (in_array($fieldName, $this->primaryKey)) {
                            $clauseFields[] = "`".$fieldName."` = ".implode($parts);
                        } else {
                            $updateFields[] = "`".$fieldName."` = ".implode($parts);
                        }
                    } else {
                        throw LookupException::populateParametersInvalid(static::class);
                    }
                } else {
                    throw LookupException::populateParametersInvalid(static::class);
                }
            // just a plain value
            } else {
                $alias = $this->convertIntToAlpha($fieldCount);
                $values[$alias] = $row[$fieldName];
                $fieldCount++;
                if (in_array($fieldName, $this->primaryKey)) {
                    $clauseFields[] = "`".$fieldName."` = :".$alias;
                } else {
                    $updateFields[] = "`".$fieldName."` = :".$alias;
                }
            }
        }
        return [
            'query' => "UPDATE `".$this->primaryTableName()."` "."\n"
                        ."SET ".implode(", ", $updateFields)." "."\n"
                        ."WHERE ".implode(" AND ", $clauseFields),
            'values' => $values
        ];
    }

    /**
     * Build a query to remove the given rows from the comparison table
     *
     * @param array $rows The rows to delete.
     * @return array
     */
    private function deleteFromComparisonTableQuery(array $rows): array
    {
        $return = $this->buildQueryParts($this->primaryKey, $rows);
        $return['query'] = "DELETE FROM `".$this->comparisonTableName()."`\n"
                            ."WHERE ".$return['query'];
        return $return;
    }

    /**
     * Build a query to delete rows of values from the primary-table, that match the left over rows in the comparison
     * table
     *
     * @return string
     * @throws LookupException Thrown when % characters are left in the table name after replacements have been made.
     */
    protected function deleteLeftOversFromPrimaryTableQuery(): string
    {
        $clauseFields = [];
        foreach ($this->primaryKey as $fieldName) {
            $clauseFields[] = "`a`.`".$fieldName."` = `b`.`".$fieldName."`";
        }
        return "DELETE `a` FROM `".$this->primaryTableName()."` AS `a`\n"
                ."JOIN `".$this->comparisonTableName()."` AS `b`\n"
                ."ON ".implode(" AND ", $clauseFields);
    }





    /**
     * Check that the fields used by this class are all defined and set up properly
     *
     * @return boolean
     * @throws SettingsException Thrown when something is wrong with the fields/primary-key/indexes.
     */
    private function checkFields(): bool
    {
        if (!count($this->primaryKey)) {
            throw SettingsException::noLookupTablePrimaryKey(static::class);
        }

        $allIndexes = [
            'Primary-key' => [$this->primaryKey],
            'Unique index' => $this->unique,
            'Regular index' => $this->indexes,
            'Fulltext index' => $this->fulltext,
        ];
        foreach ($allIndexes as $indexType => $indexes) {
            foreach ($indexes as $index) {
                foreach ($index as $field) {
                    if (!in_array($field, $this->fields)) {
                        throw SettingsException::indexFieldNotFound($indexType, $field, static::class);
                    }
                }
            }
        }

        // ...

        return true;
    }

    /**
     * Build a string of alphabetic characters based on the given integer
     *
     * eg. a, b, c, ... z, aa, ab, .. zz, aaa, aab ... etc
     *
     * @param integer $fieldCount The number to generate alpha characters for.
     * @return string
     */
    private static function convertIntToAlpha(int $fieldCount)
    {
        $fieldCount = max(0, $fieldCount);
        $string = '';
        do {
            $remainder = (int) fmod($fieldCount, 26);
            $string = chr(97 + $remainder).$string;
            $continue = false;
            if ($fieldCount >= 26) {
                $fieldCount -= $remainder;
                $fieldCount = ($fieldCount / 26) - 1;
                $continue = true;
            }
        } while ($continue);
        return $string;
    }
}
