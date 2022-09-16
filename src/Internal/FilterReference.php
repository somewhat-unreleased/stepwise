<?php

namespace CodeDistortion\Stepwise\Internal;

/**
 * A reference to a filter being applied during the Stepwise search process
 */
class FilterReference
{
    /**
     * The prefix to use when creating temp-tables
     *
     * @var string
     */
    protected $tempTablePrefix = null;

    /**
     * The primary key fields to use when creating temp tables (if collected yet)
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
     * The number of temp-tables created (is passed by ref to the constructor and is used in the temp-table names)
     *
     * @var integer
     */
    protected $tempTableCount = 0;

    /**
     * The temp-tables this object has created
     *
     * @var array
     */
    protected $tempTables = [];

    /**
     * The TempTable currently being used by this object (ie. the latest one)
     *
     * @var TempTable|null
     */
    protected $curTempTable = null;

    /**
     * The most recent TempTable populated before this filter-ref was created (ie. by the previous filter-ref)
     *
     * @var TempTable|null
     */
    protected $initialTempTable = null;

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
     * @param string    $tempTablePrefix  The prefix to use when creating temp-tables.
     * @param array     $primaryKeyFields The primary key fields to use when creating temp tables (if collected yet).
     * @param array     $fieldDefinitions The field database definitions.
     * @param integer   $tempTableCount   The number of temp-tables created (is passed by ref and is used in the
     *                                    temp-table names).
     * @param TempTable $initialTempTable The TempTable currently being used by this object (ie. the latest one).
     */
    public function __construct(
        string $tempTablePrefix,
        array $primaryKeyFields,
        array $fieldDefinitions,
        int &$tempTableCount,
//        ?TempTable $initialTempTable // @TODO PHP 7.1
        TempTable $initialTempTable = null
    ) {
        $this->tempTablePrefix = $tempTablePrefix;
        $this->primaryKeyFields = $primaryKeyFields;
        $this->fieldDefinitions = $fieldDefinitions;
        $this->tempTableCount =& $tempTableCount;
        $this->initialTempTable = $initialTempTable;
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
     * Check whether the given field is currently being tracked
     *
     * @param string $name The field's name.
     * @return boolean
     */
    public function hasField(string $name): bool
    {
        $curTempTable = $this->curTempTable();
        return ($curTempTable ? $curTempTable->hasField($name) : false);
    }

    /**
     * Returns the full list of fields and their definitions
     *
     * @return array
     */
    public function getFieldDefinitions(): array
    {
        return $this->fieldDefinitions;
    }

    /**
     * Create a new TempTable and record for use later
     *
     * @return TempTable
     */
    public function newTempTable(): TempTable
    {
        $this->tempTableCount++;
        $tempTable = (new TempTable(
            $this->tempTablePrefix.$this->tempTableCount, // table name
            $this->primaryKeyFields,
            $this->fieldDefinitions
        ))
        ->setRunQueries($this->runQueries)
        ->setQueryTracker($this->queryTracker);

        $this->tempTables[$tempTable->getTableName()] = $tempTable;
        return $tempTable;
    }

    /**
     * Use the given TempTable (as the "current" one)
     *
     * @param TempTable $tempTable The TempTable to store.
     * @return self
     */
    public function useTempTable(TempTable $tempTable): self
    {
        $this->curTempTable = $tempTable;

        return $this; // chainable
    }

    /**
     * Return the TempTable currently being used
     *
     * In none have been used yet, it returns the one created before this filter-ref object was created.
     * @return TempTable|null
     */
//    public function curTempTable(): ?TempTable // @TODO PHP 7.1
    public function curTempTable()
    {
        return ($this->curTempTable ? $this->curTempTable : $this->initialTempTable);
    }

    /**
     * Return the TempTable objects stored here
     *
     * @return array
     */
    public function getTempTables(): array
    {
        return $this->tempTables;
    }
}
