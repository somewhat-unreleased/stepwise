<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Internal\Clause;
use CodeDistortion\Stepwise\Internal\FilterReference;
use CodeDistortion\Stepwise\Internal\QueryTracker;
use CodeDistortion\Stepwise\Internal\StepwiseCallerSettables;
use CodeDistortion\Stepwise\Internal\TempTable;
use DB;
use Illuminate\Support\Collection;

/**
 * A tool used to handle the output from a Stepwise based search
 */
class Results
{
    /**
     * The Stepwise to gather results from
     *
     * @var Stepwise
     */
    private $stepwise;

    /**
     * The current FilterReference (whose current TempTable in particular is used to gather results from)
     *
     * @var FilterReference
     */
    private $currentFilterRef;

    /**
     * Cache of the result count
     *
     * @var integer|null
     */
    private $resultCountCache = null;



    /**
     * An alias for the temp-table when fetching the results
     *
     * @var string|null
     */
    private $tableAlias = 'results';

    /**
     * The fields to get when fetching the results
     *
     * (when empty, all the fields will be fetched)
     * @var array
     */
    private $fields = [];

    /**
     * The order-by fields/aliases to use when fetching the results
     *
     * @var array
     */
    private $orderBy = [];

    /**
     * When true, the orderBy values will be reset when orderBy() is next run
     *
     * The Stepwise class will set the initial orderBy values, however this setting lets it be reset as soon as the
     * caller wants to set it to something else.
     * @var bool
     */
    private $resetOrderByNextTime = false;

    /**
     * The clauses to add to the query when fetching the results
     *
     * @var array
     */
    private $clauses = [];

    /**
     * The page to return (starts at 1)
     *
     * @var integer|null
     */
    private $page = null;

    /**
     * The size of each page (the number of rows to return)
     *
     * @var integer|null
     */
    private $pageSize = null;

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
     * Results constructor
     *
     * @param Stepwise $stepwise The Stepwise INSTANCE to use (the temp-tables are gleaned from this).
     */
    public function __construct(Stepwise $stepwise)
    {
        $this->stepwise = $stepwise;
        $this->currentFilterRef = $stepwise->getTaggedFilterRef(); // use the 'allFilters" tag
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
     * Fetch the results (from the current temp-table) and return them as a Collection
     *
     * @return Collection
     */
    public function get(): Collection
    {
        return new Collection($this->raw());
    }

    /**
     * Fetch the results (from the current temp-table) and return them as a an array of StdClass objects
     *
     * @return array
     */
    public function raw(): array
    {
//        [$query, $data] = array_values($this->buildQuery()); // @TODO PHP 7.1
        list($query, $data) = array_values($this->buildQuery());

        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query, $data);
        }
        if ($this->runQueries) {
            return DB::select($query, $data);
        }
        return [];
    }

    /**
     * Find the total number of rows (in the current temp-table) based on the current Clauses
     *
     * @return integer
     */
    public function count(): int
    {
        // use the cached value if available
        if (!is_null($this->resultCountCache)) {
            return $this->resultCountCache;
        }

//        [$query, $data] = array_values($this->buildQuery(true)); // @TODO PHP 7.1
        list($query, $data) = array_values($this->buildQuery(true));

        if ($this->queryTracker) {
            $this->queryTracker->trackQuery($query, $data);
        }
        if ($this->runQueries) {
            $rows = DB::select($query, $data);
            $this->resultCountCache = $rows[0]->total;
        } else {
            $this->resultCountCache = 0;
        }

        return $this->resultCountCache;
    }

    /**
     * Reset the count cache
     *
     * Called when something changes that may affect the value.
     */
//    protected function resetCountCache(): void // @TODO PHP 7.1
    protected function resetCountCache()
    {
        $this->resultCountCache = null;
    }

    /**
     * Find the total number of pages
     *
     * @return integer|null
     */
//    public function totalPages(): ?int // @TODO PHP 7.1
    public function pageCount()
    {
        // only return a page-count when the page-size has been set
        if (!$this->pageSize) {
            return null;
        }

        $count = $this->count();
        return (int) ceil($count / $this->pageSize);
    }

    /**
     * Build a query to fetch the results (from the current temp-table)
     *
     * @param boolean $getCount When true the query will exclude some things so it can fetch the COUNT(*).
     * @return array
     */
    protected function buildQuery(bool $getCount = false): array
    {
        $data = [];
        $fields = ($getCount ? ["COUNT(*) as `total`"] : $fields = $this->getFields());
        $table = $this->getTable();
        $alias = $this->getTableAlias();
        $clauses = $this->getClauses($data);
        $orderBy = ($getCount ? [] : $this->getOrderBy());
        $limit = ($getCount ? [] : $this->getLimit($data));

        $query = "SELECT ".implode(", ", $fields)." "
                ."FROM `".$table."`"
                .($alias ? " AS `".$alias."`" : "")
                .(count($clauses) ? " WHERE ".implode(" AND ", $clauses) : "")
                .(count($orderBy) ? " ORDER BY ".implode(", ", $orderBy) : "")
                .($limit ? " ".$limit : "");
//dump($query, $data);
        return [
            'query' => $query,
            'data' => $data,
        ];
    }





    /**
     * Use a different tagged FilterReference
     *
     * @param string $tagName The tag to use.
     * @return self
     */
    public function useTag(string $tagName): self
    {
        $this->currentFilterRef = $this->stepwise->getTaggedFilterRef($tagName);
        $this->resetCountCache();

        return $this; // chainable
    }

    /**
     * Specify an alias for the temp-table
     *
     * @param string|null $alias The table alias to use.
     * @return self
     */
//    public function tableAlias(?string $alias): self // @TODO PHP 7.1
    public function tableAlias($alias): self
    {
        $this->tableAlias = $alias;

        return $this; // chainable
    }

    /**
     * Specify the fields to fetch
     *
     * @param array $fields The fields to fetch.
     * @return self
     */
    public function fields(array $fields): self
    {
        $this->fields = $fields;

        return $this; // chainable
    }

    /**
     * Specify that all fields should be fetched
     * @return self
     *
     */
    public function allFields(): self
    {
        $this->fields = [];

        return $this; // chainable
    }

    /**
     * Let the caller specify the order-by fields/aliases to use
     *
     * @param array|string   $name      The name of the field/alias to use OR an array of order-by fields/aliases to
     *                                  use.
     * @param string|boolean $direction The direction to order by.
     * @return self
     */
    public function orderBy($name, string $direction = 'ASC'): self
    {
        // handle when called singularly
        if (is_string($name)) {
            return $this->orderBy([$name => $direction]);
        }

        // let this reset the orderBy list the first time the caller calls orderBy()
        if ($this->resetOrderByNextTime) {
            $this->orderBy = [];
            $this->resetOrderByNextTime = false;
        }

        // if the array keys are integers, take the value as the key
        $orderBy = [];
        foreach ($name as $tempName => $direction) {
            if (is_int($tempName)) {
                $tempName = $direction;
                $direction = 'ASC'; // assume this is the desired direction
            }
            $orderBy[$tempName] = $direction;
        }

        $this->orderBy = array_merge($this->orderBy, $orderBy);

        return $this; // chainable
    }

    /**
     * Let the caller reset the order-by fields/aliases
     *
     * @return self
     */
    public function resetOrderBy(): self
    {
        $this->orderBy = [];

        return $this; // chainable
    }

    /**
     * When true, the orderBy values will be reset when orderBy() is next run
     *
     * @return self
     */
    public function resetOrderByNextTime(): self
    {
        $this->resetOrderByNextTime = true;

        return $this; // chainable
    }





    /**
     * Add a clause to use when fetching the results
     *
     * @param Clause $clause The clause to add.
     * @return self
     */
    public function clause(Clause $clause): self
    {
        $this->clauses[] = $clause;
        $this->resetCountCache();

        return $this; // chainable
    }

    /**
     * Remove the clauses from this object
     *
     * @return self
     */
    public function removeClauses(): self
    {
        $this->clauses = [];
        $this->resetCountCache();

        return $this; // chainable
    }

    /**
     * Limit the results to return one "page" worth of rows
     *
     * @param integer $page     The page to return (starts at 1).
     * @param integer $pageSize The size of each page (the number of rows to return).
     * @return self
     */
    public function page(int $page, int $pageSize): self
    {
        $this->page = $page;
        $this->pageSize = $pageSize;

        return $this; // chainable
    }





    /**
     * Returns the current temp-table name
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->curTempTable()->getTableName();
    }

    /**
     * Returns the table-alias if set
     *
     * @return string|null
     */
//    public function getTableAlias(): ?string // @TODO PHP 7.1
    public function getTableAlias()
    {
        return $this->tableAlias;
    }

    /**
     * Returns the table-alias if set, or the temp-table's real name
     *
     * @return string
     */
    public function getEffectiveTableAlias(): string
    {
        return ($this->getTableAlias() ? $this->getTableAlias() : $this->getTable());
    }

    /**
     * Returns the fields that the temp-table has
     *
     * @return array
     */
    public function getTableFields(): array
    {
        if ($this->curTempTable()) {
            return $this->curTempTable()->getFieldNames();
        }
        return [];
    }

    /**
     * Returns the fields as they will be used when fetching results
     *
     * @return array
     */
    public function getFields(): array
    {
        // use the fields the caller specified, or use all of them
        $fields = (count($this->fields) ? $this->fields : $this->getTableFields());
        return $this->renderFields($fields);
    }

    /**
     * Returns the clauses as they will be used when fetching results
     *
     * @param array $data An array that clause values will be added to.
     * @return array
     */
    public function getClauses(array &$data): array
    {
        // build the query clauses
        $clauses = [];
        foreach ($this->clauses as $clause) {
            $clauses[] = $clause->render(
                [$this->getEffectiveTableAlias() => $this->curTempTable()],
                $data
            );
        }
        return $clauses;
    }

    /**
     * Returns the ORDER BY to be used when fetching results
     *
     * @return array
     */
    public function getOrderBy(): array
    {
        $orderBy = [];
        foreach ($this->orderBy as $name => $direction) {

            $orderASC = $this->orderIsAsc($direction);

            // if this is simply a field then use that
            if ($this->curTempTable()->hasField($name)) {

                $clause = "`".$this->getEffectiveTableAlias()."`.`".$name."`";
                $orderBy[] = $clause.' '.($orderASC ? 'ASC' : 'DESC');

            // if it's a field but it doesn't exist in this particular table, then silently skip it
            } elseif ($this->stepwise->fieldDefinitionExists($name)) {
                // do nothing
            // if this is an order-by alias, resolve its parts
            } else {

                $orderByAlias = $this->stepwise->getOrderByAlias($name);

                // each order-by-type is made up of several clauses, add each of them
                foreach ($orderByAlias as $orderByClause) {

                    // check that all the fields for this clause exist
                    $fields = $orderByClause['fields'];
                    $fields = (is_array($fields) ? $fields : [$fields]);

                    $tableHasAllFields = true;
                    foreach ($fields as $field) {
                        if (!$this->curTempTable()->hasField($field)) {
                            $tableHasAllFields = false;
                            break;
                        }
                    }

                    // if all the fields exists for this clause then use it, otherwise silently skip it
                    if ($tableHasAllFields) {

                        $clause = str_replace('%table%', $this->getEffectiveTableAlias(), $orderByClause['clause']);
                        $clauseOrderSame = $this->orderIsAsc($orderByClause['dir']);
                        $clauseOrderASC = ($orderASC == $clauseOrderSame);

                        $orderBy[] = $clause.' '.($clauseOrderASC ? 'ASC' : 'DESC');
                    }
                }
            }
        }
        return $orderBy;
    }

    /**
     * Returns the query limit to be used when fetching results
     *
     * @param array $data An array that clause values will be added to.
     * @return string|null
     */
//    public function getLimit(array &$data): ?string // @TODO PHP 7.1
    public function getLimit(array &$data)
    {
        $limit = null;
        $page = $this->page;
        $pageSize = $this->pageSize;
        if ((!is_null($page)) && (!is_null($pageSize))) {
            $page = max($page, 1);
            $start = ($page - 1) * $pageSize;

            $limit = "LIMIT ?, ?";
            $data[] = $start;
            $data[] = $pageSize;
        }
        return $limit;
    }





    /**
     * Resolves the current temp-table
     *
     * @return TempTable|null
     */
    protected function curTempTable()
    {
        return $this->currentFilterRef->curTempTable();
    }

    /**
     * Render the given fields ready to be fetched in a query
     *
     * @param array $fields The fields to render as text.
     * @return array
     */
    protected function renderFields(array $fields)
    {
        $realFields = (array) $this->getTableFields();
        $effectiveTableAlias = $this->getEffectiveTableAlias();

        $rendered = [];
        foreach ($fields as $index => $value) {

            if (is_int($index)) {
                $field = $alias = $value;
            } else {
                $field = $value;
                $alias = $index;
            }

            $fieldIsReal = (isset($realFields[$field]));

            if ($alias != $field) {
                $rendered[$alias] = ($fieldIsReal
                    ? "`".$effectiveTableAlias."`.`".$field."`"
                    : $field)." as `".$alias."`";
            } else {
                $rendered[$alias] = ($fieldIsReal
                    ? "`".$effectiveTableAlias."`.`".$field."`"
                    : $field);
            }
        }
        return $rendered;
    }

    /**
     * Take the given $direction and check if it is equivalent to ASC
     *
     * @param string|boolean $direction The direction to order query results in.
     * @return boolean
     */
    protected function orderIsAsc($direction): bool
    {
        return ($this->resolveOrderDir($direction) == 'ASC');
    }

    /**
     * Take the given $direction and resolve it to ASC or DESC
     *
     * @param string|boolean $direction The direction to order query results in.
     * @return string
     */
    protected function resolveOrderDir($direction): string
    {
        if (is_string($direction)) {
            return ((mb_strtoupper($direction) != 'DESC') ? 'ASC' : 'DESC');
        }
        return ($direction ? 'ASC' : 'DESC');
    }
}
