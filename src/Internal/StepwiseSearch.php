<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Clauses\RegularClause;
use CodeDistortion\Stepwise\Exceptions\ClauseException;
use CodeDistortion\Stepwise\Exceptions\SearchException;
use CodeDistortion\Stepwise\Input;
use CodeDistortion\Stepwise\Lookup;
use CodeDistortion\Stepwise\Results;
use DB;

trait StepwiseSearch
{
    use StepwiseSetup;
    use StepwiseCleanUp;
    use StepwiseTools;

    /**
     * The number of temp-tables created (is used in the temp-table names)
     *
     * @var integer
     */
    private $tempTableCount = 0;

    /**
     * The combined static::$fieldDefinitions and the fields from the Lookups
     *
     * @var array
     */
    private $resolvedFieldDefinitions = [];

    /**
     * The fields to pick up along the way when performing the search
     *
     * This contains the primary-key and order-by-type fields as well as the $this->getFieldsToTrack() ones the caller
     * may have specified.
     * @var array
     */
    private $resolvedFieldsToTrack = [];

    /**
     * The prefix to use when creating temp-tables
     *
     * @var string
     */
    private $tempTablePrefix = '';

    /**
     * The parameters that each filter method accepts
     *
     * @var array
     */
    private $filterMethodParams = [];

    /**
     * This class looks for methods defined by the child class that end in 'Filter'
     *
     * @var string
     */
    private $filterMethodSuffix = 'Filter';

    /**
     * The name of the fall-back filter method to use when no other filters are run
     *
     * @var string
     */
    private $fallbackFilterMethod = 'fallbackFilter';

    /**
     * The name of the tag used to represent where "all" of the filters have been applied
     *
     * @var string
     */
    private $allFilterTag = 'all';

    /**
     * The name of the filter-alias used to represent "all" of the filter methods
     *
     * @var string
     */
    private $allFilterAlias = 'allFilters';

    /**
     * A list of methods to skip when looking for filter-methods
     *
     * @var array
     */
    private $filterMethodSkipList = ['regularFilter'];

    /**
     * The parameter passed to each filter-method and it indicates whether the method should run the filter, or just
     * report which action will occur
     *
     * @var string
     */
    private $actionCheckParam = 'actionCheck';

    /**
     * The parameter passed to each filter-method and indicating whether it is allowed to 'alter' the rows
     *
     * @var string
     */
    private $allowAlterParam = 'allowAlter';

    /**
     * References kept for each filter that's run
     *
     * @var array
     */
    private $filterRefs = [];

    /**
     * The filter-ref that's currently being used
     *
     * @var FilterReference|null
     */
    private $currentFilterRef = null;

    /**
     * The FilterRefs that were tagged during the filter-pathing process
     *
     * @var array
     */
    private $filterRefTags = [];

    /**
     * Whether the search has run yet?
     *
     * Limits some actions afterwards.
     * @var boolean
     */
    private $searchIsPending = true;





    /**
     * Constructor
     */
    public function __construct()
    {
        // find xyzFilter methods in this (child) class
        $this->filterMethodParams = $this->findFilterMethods(
            $this->filterMethodSuffix,
            $this->fallbackFilterMethod,
            $this->filterMethodSkipList,
            $this->actionCheckParam,
            $this->allowAlterParam
        );

        // check that the fields referred to is this class and each Lookup class are all ok!
        $this->resolvedFieldDefinitions = $this->resolveFieldDefinitions(
            $this->fieldDefinitions,
            $this->lookupClasses
        );

        $this->tempTablePrefix = 'temp_precache_lookup_'.mt_rand().'_';
    }

    /**
     * Destructor
     */
    public function __destruct()
    {
        $this->dropTempTables();
    }





    /**
     * Perform the search
     *
     * @return Results
     */
    public function search(): Results
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->searchIsPending = false;

        // resolve the Input to use
        $input = $this->getCallerInput();
        if (!$input) {
            // build an empty one if needed
            $class = $this->inputClass;
            $input = new $class();
        }

        if ($input->searchCanProceed()) {
// dump('PREPARING');

            $primaryKeyFields = $this->getCallerPrimaryKey();
            if (is_null($primaryKeyFields)) {
                $primaryKeyFields = $this->defaultPrimaryKey;
            }

            // add the primary-key and order-by fields to the list of fields to track
            $this->resolvedFieldsToTrack = array_unique(
                array_values(
                    array_merge(
                        $primaryKeyFields,
                        $this->getOrderByFields($this->getCallerOrderBy()),
                        $this->getCallerFieldsToTrack()
                    )
                )
            );



            // build lists of the filters that will be run, broken down by whether they alter the rows,
            // and which fields they can add (ie. "update")
//            [$alterFilters, $updateFilters, $linkFields] = array_values( // @TODO PHP 7.1
            list($alterFilters, $updateFilters, $linkFields) = array_values(
                $this->findRunnableFilters($this->filterMethodParams, $input)
            );

            // build the list of tables to tag
            $filterTagList = $this->buildFilterTagList($this->filterMethodParams, $this->getCallerTags());

            // organise the $filterTagList so the order the filters are applied overlap better
            $filterTagList = $this->organiseFilterTagList(
                $filterTagList,
                $alterFilters,
                $updateFilters,
                $this->resolvedFieldsToTrack
            );

            // add the fields that link tables together, to the list of fields to track
            $this->resolvedFieldsToTrack = $this->addLinkFields(
                $filterTagList,
                $this->resolvedFieldsToTrack,
                $linkFields
            );

// dump('RUNNING');
// dump($filterTagList);
            $this->runSearchFilterPathing($primaryKeyFields, $filterTagList, $this->filterMethodParams, $input);
        }

        return $this->results();
//        return $this; // chainable
    }





    /**
     * Build a list of the filters that will be run
     *
     * This is based on the $input' data, work out which filter-methods will be run.
     *
     * @param array $filterMethodParams The filter-methods and parameters that exist in this class.
     * @param Input $input              The values the caller has requested to filter by.
     *
     * @return array
     */
    private function findRunnableFilters(array $filterMethodParams, Input $input): array
    {
        $alterFilters = $updateFilters = $linkFields = [];
        foreach ($filterMethodParams as $filterMethod => $filterParameters) {

            $args = $this->buildFilterParameters(
                $input,
                $filterParameters,
                true, // $allowAlter
                true // $actionCheck
            );

            $callable = [$this, $filterMethod];
            $result = false;
            if (is_callable($callable)) { // to please phpstan
                $result = call_user_func_array($callable, $args);
            }
            if ($result !== false) {

                // turn the "track" and "links" values into a associative arrays
                // (this allows the response to be a string, or 0-indexed array as well as an assoc array)
                if (is_array($result)) {
                    foreach (['track', 'link'] as $type) {
                        if (array_key_exists($type, $result)) {
                            $result[$type] = $this->buildAssocArray($result[$type]);
                        }
                    }
                }

                // check the result was valid
                if ((!is_array($result))
                    // whether the rows are altered
                    || (!array_key_exists('alter', $result))
                    // the fields that can be added
                    || (!array_key_exists('track', $result)) || (!is_array($result['track']))) {

                    throw SearchException::invalidFilterActionCheckResponse(
                        static::class,
                        $filterMethod,
                        $this->actionCheckParam
                    );
                }

                // check if this method will ALTER rows
                if ($result['alter']) {
                    $alterFilters[] = $filterMethod;
                }

                // check if this method will UPDATE existing rows
                if (count($result['track'])) {
                    foreach ($result['track'] as $fieldName => $expression) {
                        // remove fields where the definition is empty
                        // (it may be set to use a clause that wasn't created)
                        if ($expression != null) { // equivalent to stripping off using array_filter(..)
                            $updateFilters[$fieldName][] = $filterMethod;
                        }
                    }
                }

                // record which fields link this filter to the previous one
                if (count($result['link'])) {
                    $linkFields[$filterMethod] = $result['link'];
                }
            }
        }

        return [
            'alterFilters' => $alterFilters,
            'updateFilters' => $updateFilters,
            'linkFields' => $linkFields,
        ];
    }

    /**
     * Build the list of tables to tag, based on the user defined list of filters to tag (which may include
     * filter-aliases)
     *
     * @param array $filterMethodParams The filter-methods and parameters that exist in this class.
     * @param array $tags               The user-defined list of tables to tag.
     * @return array
     */
    private function buildFilterTagList(array $filterMethodParams, array $tags): array
    {
        unset($tags[$this->allFilterTag]); // make sure the tag 'allFilters' wasn't set by the caller
        $tags = array_merge(
            [$this->allFilterTag => ['+'.$this->allFilterAlias]], // put the 'all' tag at the front
            $tags
        );

        // define the filter-aliases
        $filterAliases = $this->filterAliases;
        // add the 'allFilters' filter-alias (now we know which filter-methods exist)
        $filterAliases[$this->allFilterAlias] = array_keys($filterMethodParams);

        // inspect each tag's list of filters to include/exclude
        $filterTagList = [];
        foreach ($tags as $tagName => $filterList) {

            // implicitly start with all filters enabled for this tag (ie. '+allFilters')
            if ((!in_array('+'.$this->allFilterAlias, $filterList))
            && (!in_array('-'.$this->allFilterAlias, $filterList))) {
                $filterList = array_merge(['+'.$this->allFilterAlias], $filterList);
            }

            $tagFilters = [];
            foreach ($filterList as $filterAlias) {

                // work out if this filter-alias should be included or removed (first char is a + or -)
                $firstChar = mb_substr($filterAlias, 0, 1);
                if (!in_array($firstChar, ['+', '-'])) {
                    throw SearchException::invalidFilterAlias($filterAlias);
                }
                $keep = ($firstChar == '+'); // add or remove from the $filterAlias list
                $filterAlias = mb_substr($filterAlias, 1); // pick out the filter name

                // if this $filterAlias refers to a particular filter-method, then use that method
                if (isset($filterMethodParams[$filterAlias])) {
                    $newFilters = [$filterAlias];
                // otherwise resolve the filter-alias (if it exists)
                } elseif (isset($filterAliases[$filterAlias])) {
                    $newFilters = $filterAliases[$filterAlias];
                // otherwise just add it so it's picked up as not existing below
                } else {
                    $newFilters = [$filterAlias];
                }

                // make sure the filterAlias resolved to valid filter-names
                foreach ($newFilters as $filterName) {
                    if (!array_key_exists($filterName, $filterMethodParams)) {
                        throw SearchException::filterNotFound($filterName);
                    }
                }

                // add $newFilters to the list of filters
                if ($keep) {
                    $tagFilters = array_unique(array_merge($tagFilters, $newFilters));
                } else { // or remove them
                    $tagFilters = $this->removeByValue($tagFilters, $newFilters);
                }
            }

            // make sure the fallbackAlias has been added
            // (it may be removed again later on, but in case it's needed it'll be there)
            if ((isset($this->filterMethodParams[$this->fallbackFilterMethod]))
            && (!in_array($this->fallbackFilterMethod, $tagFilters))) {
                $tagFilters[] = $this->fallbackFilterMethod;
            }

            $filterTagList[$tagName] = array_values($tagFilters);
        }
        return $filterTagList;
    }

    /**
     * Take the desired $filterTagList, and put them in the order they should be executed so the individual filters
     * overlap as much as possible (and don't include any filters that shouldn't be run)
     *
     * @param array $filterTagList The list of desired $filterTagList.
     * @param array $alterFilters  The "alter" filters that will be run.
     * @param array $updateFilters The "update" filters that will be run.
     * @param array $fieldsToTrack The fields tht need to be tracked along the way.
     * @return array
     */
    private function organiseFilterTagList(
        array $filterTagList,
        array $alterFilters,
        array $updateFilters,
        array $fieldsToTrack
    ): array {

        // determine which 'alter' filters need to run per tag
        foreach ($filterTagList as $tagName => $filterList) {

            // add the "alter" filters
            $usedAlterFilters = [];
            foreach ($alterFilters as $filter) {
                if (in_array($filter, $filterList)) {
                    $usedAlterFilters[] = $filter;
                }
            }
            // if the "fallbackFilter" is present, but there are others, remove the fallback
            if ((in_array($this->fallbackFilterMethod, $usedAlterFilters)) && (count($usedAlterFilters) > 1)) {
                $usedAlterFilters = $this->removeByValue($usedAlterFilters, $this->fallbackFilterMethod);
            }

            $filterTagList[$tagName] = $usedAlterFilters;
        }

        // put all the filters in order of frequency
        $frequencies = [];
        foreach (array_keys($filterTagList) as $tagName) {
            foreach ($filterTagList[$tagName] as $filter) {
                $frequencies[$filter] = ($frequencies[$filter] ?? 0) + 1;
            }
        }
        arsort($frequencies);

        // re-arrange the filters per tag to be in order of frequency (so the common ones get run first)
        foreach (array_keys($filterTagList) as $tagName) {
            $tempFilters = [];
            foreach (array_keys($frequencies) as $filter) {
                if (in_array($filter, $filterTagList[$tagName])) {
                    $tempFilters[] = $filter;
                }
            }
            // make sure it has some filters by the end
            if (!count($tempFilters)) {
                throw SearchException::tagGroupHasNoFilters($tagName);
            }

            $filterTagList[$tagName] = $tempFilters;
        }



        // see if any filter-methods need to be run in "update"-only mode
        foreach ($updateFilters as $fieldName => $possibleFilters) {

            // only try to add if we're going to track it
            if (in_array($fieldName, $fieldsToTrack)) {
                foreach ($filterTagList as $tagName => $filterList) {

                    $found = false;
                    foreach ($possibleFilters as $possibleFilter) {
                        if ((in_array($possibleFilter, $filterList))
                            || (in_array($possibleFilter.'/update-only', $filterList))) {
                            $found = true;
                            break;
                        }
                    }
                    // if a filter-method that can populate $fieldName wasn't found, add one
                    if (!$found) {
                        // indicate this filter should run, but not allow it to "alter" the rows
                        $firstPossibleFilter = reset($possibleFilters);
                        $filterTagList[$tagName][] = $firstPossibleFilter.'/update-only';
                    }
                }
            }
        }

        return $filterTagList;
    }

    /**
     * Add the fields that link tables together to the list of fields to track
     *
     * @param array $filterTagList The filter-pathing steps that will be taken.
     * @param array $fieldsToTrack The original fields to track.
     * @param array $linkFields    The fields that link each filter step to the previous table.
     * @return array
     */
    private function addLinkFields(array $filterTagList, array $fieldsToTrack, array $linkFields)
    {
        foreach ($filterTagList as $filterList) {
            foreach ($filterList as $filterMethod) {

                // remove "/update-only" from the method name
                $updateOnly = mb_substr($filterMethod, -12) == '/update-only';
                $filterMethod = ($updateOnly ? mb_substr($filterMethod, 0, -12) : $filterMethod);

                // add any fields that link this filter's table to the previous one
                if (isset($linkFields[$filterMethod])) {
                    $fieldsToTrack = array_unique(
                        array_merge(
                            $fieldsToTrack,
                            array_keys($linkFields[$filterMethod]),
                            array_values($linkFields[$filterMethod])
                        )
                    );
                }
            }
        }
        return $fieldsToTrack;
    }





    /**
     * Perform the queries, tag the filter-refs
     *
     * @param array $primaryKeyFields   The primary-key fields to use when creating tmp tables (if collected yet).
     * @param array $filterTagList      The filters to tag.
     * @param array $filterMethodParams The list of all filter-method parameters.
     * @param Input $input              The Input containing the input values to apply during the search.
     * @return void
     */
    private function runSearchFilterPathing(
        array $primaryKeyFields,
        array $filterTagList,
        array $filterMethodParams,
        Input $input
//    ): void { // @TODO PHP 7.1
    ) {

        foreach ($filterTagList as $tagName => $tagFilters) {

            $this->currentFilterRef = $filterRefHash = null; // $filterRefHash represents each filterRef
            foreach ($tagFilters as $filterMethod) {

                // look for "/update-only" in the filter name
                $updateOnly = mb_substr($filterMethod, -12) == '/update-only';
                $filterMethod = ($updateOnly ? mb_substr($filterMethod, 0, -12) : $filterMethod);
                $fields = (isset($this->filterRefs[$filterRefHash])
                    ? $this->filterRefs[$filterRefHash]->getFieldDefinitions()
                    : []
                );



                // build a new hash based on the things that make this filter-ref unique
                $filterParameters = $filterMethodParams[$filterMethod];
                $filterRefHash = md5(serialize([
                    $filterRefHash, // reflect the previous hashes (each one contains the previous hash)
                    $filterMethod,
                    $primaryKeyFields,
                    $fields,
                    $filterParameters,
                    $updateOnly
                ]));
                // apply this filter if it hasn't been applied yet
                if (!isset($this->filterRefs[$filterRefHash])) {
// dump('running filter: '.$filterMethod);
                    // build a new reference for this filter
                    $this->currentFilterRef = $this->filterRefs[$filterRefHash] = $this->newFilterRef(
                        $primaryKeyFields,
                        $fields
                    );

                    // call the method that performs the filter, (which was defined by the child class)
                    $args = $this->buildFilterParameters(
                        $input,
                        $filterParameters,
                        !$updateOnly, // $allowAlter
                        false // $actionCheck
                    );

                    $callable = [$this, $filterMethod];
                    if (is_callable($callable)) { // to please phpstan
                        call_user_func_array($callable, $args);
                    }

                } else {
                    $this->currentFilterRef = $this->filterRefs[$filterRefHash];
                }
            }
// dump('tagging "'.$tagName.'"');
            $this->filterRefTags[$tagName] = $filterRefHash;
        }

        // leave the currently-selected filter-tag as "all", which contains all the refinements
//        $this->useTag($this->allFilterTag);
    }

    /**
     * Build parameters from the given $input values
     *
     * These will be passed to the filter methods when they're run.
     * @param Input   $input            The filter-values to use when building the parameters.
     * @param array   $filterParameters The parameter names needed.
     * @param boolean $allowAlter       Will the filter-method be allowed to 'alter' the rows?.
     * @param boolean $actionCheck      The $actionCheck parameter to add to the end.
     * @return array
     */
    private function buildFilterParameters(
        Input $input,
        array $filterParameters,
        bool $allowAlter,
        bool $actionCheck
    ): array {
        $args = [];
        foreach ($filterParameters as $reflectionParameter) {

            // pass the value from the Input
            if ($allowAlter) {
                // change nulls to empty arrays if the parameter is expecting an array
                // (just to make the filter-methods smoother to work with)
                $value = $input->{$reflectionParameter->name};
                if (!is_null($value)) {
                    $args[] = $value;
                } else {
                    $type = ($reflectionParameter->getType()
                        ? Compatibility::parameterType($reflectionParameter->getType())
                        : null);
                    $args[] = ($type == 'array' ? [] : null);
                }
                // or pass nulls/empty arrays
            } else {
                $type = ($reflectionParameter->getType()
                    ? Compatibility::parameterType($reflectionParameter->getType())
                    : null);
                $args[] = ($type == 'array' ? [] : null);
            }
        }
        $args[] = $allowAlter;
        $args[] = $actionCheck;
        return $args;
    }

    /**
     * Create a new FilterRef object
     *
     * @param array $primaryKeyFields The fields to use as the primary-key for the temp-table/s created.
     * @param array $fields           The field-definitions for the temp-tables to use.
     * @return FilterReference
     */
    protected function newFilterRef(array $primaryKeyFields, array $fields): FilterReference
    {
        return (new FilterReference(
            ($this->getTestMode() ? 'temp_table_' : $this->tempTablePrefix), // use a predictable testing name
            $primaryKeyFields,
            $fields,
            $this->tempTableCount,
            $this->curTempTable()
        ))
            ->setRunQueries($this->getRunQueries()) // should queries be run?
            ->setQueryTracker($this->getCallerQueryTracker()); // pass the query-tracker (if it's been set)
    }

    /**
     * Use the current-filter-ref to create a new temp-table object
     *
     * Note: this won't actually create the temp table in the database. Running $tempTable->createTempTable will do
     * this)
     *
     * @param string|array            $extraTrackFields    The new fields that could be tracked.
     * @param string|array            $linkingFields       The new fields that link this table to the previous one.
     * @param TempTable|Lookup|string $refTable            The ref-table (usually an object which knows details
     *                                                     about that table).
     * @param boolean                 $actuallyCreateTable Creates the temp table in the database straight away
     *                                                     when true.
     * @return TempTable
     */
    protected function newTempTable(
        $extraTrackFields,
        $linkingFields,
        $refTable = null,
        bool $actuallyCreateTable = true
    ): TempTable {

        if (!$this->currentFilterRef) {
            throw SearchException::noFilterReference();
        }

        // add the $linkingFields and $refTable's fields to the $extraTrackFields
        $canTrackFields = $this->buildCanTrackFields($extraTrackFields, $linkingFields, $refTable);
        // just pick out the fields that need to be tracked
        $fieldsToTrack = array_keys($this->pickFieldsToTrack($canTrackFields));



        // create the new temp-table object
        $tempTable = $this->currentFilterRef->newTempTable();

        // add definitions for the fields that need to be included in this table
        foreach ($fieldsToTrack as $field) {
            $field = (string) $field; // for phpstan
            if (array_key_exists($field, $this->resolvedFieldDefinitions)) {
                $tempTable->trackField($field, $this->resolvedFieldDefinitions[$field]);
                $this->currentFilterRef->trackField($field, $this->resolvedFieldDefinitions[$field]);
            } else {
                throw SearchException::untrackableField($field);
            }
        }

        // actually create the table in the database if desired
        if ($actuallyCreateTable) {
            $tempTable->createTempTable();
        }

        return $tempTable;
    }





    /**
     * Perform the query to select from a $srcTable, $refTable (optional) and insert into a $destTable
     *
     * @param TempTable                    $srcTable         The current TempTable (or null).
     * @param TempTable|Lookup|string|null $refTable         The table to use as a reference,
     *                                                       (this contains the data being checked against).
     * @param TempTable                    $destTable        The TempTable the results will be inserted into.
     * @param string|array                 $linkingFields    The fields that link the $srcTable and the $refTable.
     *                                                       either:
     *                                                       - an array: [src.fld1 => ref.fld1, src.fld2 => ref.fld2]
     *                                                       - an array: [field1, field2]
     *                                                       - a string: "field".
     * @param string|array                 $extraTrackFields The additional fields to pick up along the way.
     * @param array                        $clauseSet        The clause (refinements) to use when selecting the
     *                                                       data.
     * @param boolean                      $leftJoin         Should the join be a regular or LEFT join?
     *                                                       (if left, it will still copy the rows even when they
     *                                                       don't exist in the $refTable).
     * @return self
     */
    protected function runFilterQuery(
//        ?TempTable $srcTable, // ie. the current temp-table // @TODO PHP 7.1
        TempTable $srcTable = null, // ie. the current temp-table
        $refTable, // a TempTable or Lookup
        TempTable $destTable,
        $linkingFields,
        $extraTrackFields,
//        ?array $clauseSet, // @TODO PHP 7.1
        array $clauseSet = null,
        bool $leftJoin
    ): self {

        // turn the $refTable into an abject if needed (and possible)
        $refTable = $this->resolveRefTable($refTable);

        // if the srcTable doesn't exist then just the refTable instead
        // (srcTable won't exist if this is the first filter being run)
        if (is_null($srcTable)) {
            $srcTable = $refTable;
            $refTable = null;
        }





        // add the $linkingFields and $refTable's fields to the $extraTrackFields
        $canTrackFields = $this->buildCanTrackFields($extraTrackFields, $linkingFields, $refTable);

        // build the list of ALL fields to select
        $fieldsToTrack = $this->pickFieldsToTrack($destTable->getFieldNames(), $canTrackFields);
        $selectParts = $queryValues = [];
        foreach ($fieldsToTrack as $fieldName => $expression) {

            $fieldName = (string) $fieldName; // for phpstan

            // check if this field was tracked previously
            // get from the srcTable
            if ((is_object($srcTable)) && ($this->isTableObj($srcTable)) && ($srcTable->hasField($fieldName))) {
                $selectParts[$fieldName] = "`src`.`".$fieldName."`";
                // or get from the refTable
            } elseif ((is_object($refTable)) && ($this->isTableObj($refTable)) && ($refTable->hasField($fieldName))) {
                $selectParts[$fieldName] = "`ref`.`".$fieldName."`";
                // if the refTable is a string, just assume the field belongs to that
            } elseif ((is_string($refTable)) && (mb_strlen($refTable))) {
                $selectParts[$fieldName] = "`ref`.`".$fieldName."`";
                // if the expression is a clause object then have that render the clause string
            } elseif ($expression instanceof Clause) {
                $renderedExpression = $expression->render(['src' => $srcTable, 'ref' => $refTable], $queryValues);
                if ($renderedExpression) {
                    $selectParts[$fieldName] = $renderedExpression;
                }
                // or use the expression as it is
            } else {
                $tableAlias = $this->pickFieldTableAlias(
                    ['src' => $srcTable, 'ref' => $refTable],
                    $fieldName,
                    false
                );
                if ($tableAlias) {
                    $selectParts[$fieldName] = "`".$tableAlias."`.`".$expression."`";
                }
            }
        }
        array_walk($selectParts, function (&$value, $key) {
            $value = $value." as `".$key."`";
        });



        // build the join parts
        $joinParts = [];
        if (!is_null($refTable)) {
            $linkingFields = $this->buildAssocArray($linkingFields);
            foreach ($linkingFields as $index => $value) {

                // check that the fields exist in tables
                $this->pickFieldTableAlias(['src' => $srcTable], (string) $index);
                $this->pickFieldTableAlias(['ref' => $refTable], $value);
                $joinParts[] = "`src`.`".$index."` = `ref`.`".$value."`";
            }
        }



        // resolve the refinement criteria
        $refinementParts = [];
        $clauseSet = array_filter(is_array($clauseSet) ? $clauseSet : []);
        foreach ($clauseSet as $fieldName => $clause) {

            if ((!is_object($clause)) && (!is_int($fieldName))) {
                $clause = new RegularClause($fieldName, '=', $clause);
            }
            if ($clause instanceof Clause) {
                if ($renderedExpression = $clause->render(['src' => $srcTable, 'ref' => $refTable], $queryValues)) {
                    $refinementParts[] = $renderedExpression;
                }
            } elseif (is_string($clause)) {
                $refinementParts[] = $clause;
            } else {
                throw SearchException::unknownClause();
            }
        }
// dump($clauseSets);
// dump($refinementParts, $queryValues);


        // build the query to run
        $srcTableName = ((is_object($srcTable)) && ($this->isTableObj($srcTable))
            ? $srcTable->getTableName()
            : (is_string($srcTable) ? $srcTable : '')); // for phpstan
        $refTableName = ((is_object($refTable)) && ($this->isTableObj($refTable))
            ? $refTable->getTableName()
            : (is_string($refTable) ? $refTable : '')); // for phpstan

        $query = "INSERT IGNORE INTO `".$destTable->getTableName()."`\n"
            ."SELECT ".implode(', ', $selectParts)."\n"
            ."FROM `".$srcTableName."` src\n"
            .(!is_null($refTable)
                ? ($leftJoin ? "LEFT JOIN" : "JOIN")." `".$refTableName."` ref\n"
                .(count($joinParts)
                    ? "ON ".implode(" AND ", $joinParts)."\n"
                    : ""
                )
                : ""
            )
            .(count($refinementParts)
                ? "WHERE ".implode(" AND ", $refinementParts)
                : ""
            );
        $query = rtrim($query);

        if ($this->getRunQueries()) {
            DB::insert($query, $queryValues);
        }
        if ($this->getCallerQueryTracker()) {
            $this->getCallerQueryTracker()->trackQuery($query, $queryValues); // used by testing code
        }

        return $this; // chainable
    }

    /**
     * Perform a filter step - this is used to handle most filters
     *
     * @param Lookup|string     $refTable         The lookup-table to use as a reference when selecting rows.
     * @param string|array      $linkingFields    The fields that link the $srcTable and the $refTable. either:
     *                                            - an array: [src.field1 => ref.field1, src.field2 => ref.field2]
     *                                            - an array: [field1, field2]
     *                                            - a string: "field".
     * @param string|array|null $extraTrackFields The fields that this refinement could track (if needed).
     * @param array             $clauseSets       The clauses to use to refine the results.
     * @param boolean           $allowAlter       Are alterations allow, if not then only updates should be performed.
     * @param boolean           $actionCheck      Whether to actually perform the refinements, or just report
     *                                            back what will happen.
     * @param boolean           $forceAlter       When true this will force it to perform a refinement, even
     *                                            without any clauses.
     * @return array|boolean
     */
    protected function regularFilter(
        $refTable,
        $linkingFields,
        $extraTrackFields,
//        ?array $clauseSets // @TODO PHP 7.1
        array $clauseSets = null,
        bool $allowAlter,
        bool $actionCheck,
        bool $forceAlter = false
    ) {

        // add the $linkingFields and $refTable's fields to the $extraTrackFields
        $canTrackFields = $this->buildCanTrackFields($extraTrackFields, $linkingFields, $refTable);



        // check to see if any clauseSets have been passed (ie. which will ALTER the results)
        $alter = false;
        if ($allowAlter) {

            $clauseSets = array_filter(is_array($clauseSets) ? $clauseSets : []);
            foreach ($clauseSets as $index => $clauseSet) {

                $clauseSet = array_filter(is_array($clauseSet) ? $clauseSet : []);
                foreach ($clauseSet as $fieldName => $clause) {

                    // check to see if it has a refinement of some sort
                    // a Clause
                    if ($clause instanceof Clause) {
                        $alter = true;
                    // or an array defining the value/s
                    } elseif ((!is_int($fieldName))
                    && (((is_array($clause)) && (count($clause)))
                    || (!is_array($clause)))
                    ) {
                        $alter = true;
                    } else {
                        unset($clauseSets[$index][$fieldName]);
                    }
                }
                if (!count($clauseSets[$index])) {
                    unset($clauseSets[$index]);
                }
            }
            // or don't allow any alterations
        } else {
            $forceAlter = false;
            $clauseSets = [];
        }



        // proceed if this will ALTER or UPDATE the results
        $alter = (bool) ($alter | $forceAlter);
        if (($alter) || (count($canTrackFields))) {

            // if desired, just report details about what will occur
            if ($actionCheck) {
                return [
                    'alter' => $alter,
                    'track' => array_keys($canTrackFields),
                    'link' => $linkingFields,
                ];
            }



            // perform the filter
            $leftJoin = !$allowAlter;
            $clauseSets = ((is_array($clauseSets)) && (count($clauseSets)) ? $clauseSets : [null]);
            foreach ($clauseSets as $clauseSet) {

                $this->runOneFilterQueryIteration(
                    $this->curTempTable(),
                    $refTable,
                    $linkingFields,
                    $canTrackFields,
                    $clauseSet,
                    $leftJoin
                );

            }
            return true;
        }
        return false;
    }

    /**
     * Perform one filter query
     *
     * @param TempTable               $srcTable       The current TempTable (or null).
     * @param TempTable|Lookup|string $refTable       The table to use as a reference,
     *                                                (this contains the data being checked against).
     * @param string|array            $linkingFields  The fields that link the $srcTable and the $refTable.
     *                                                either:
     *                                                - an array: [src.fld1 => ref.fld1, src.fld2 => ref.fld2]
     *                                                - an array: [field1, field2]
     *                                                - a string: "field".
     * @param string|array            $canTrackFields The additional fields to pick up along the way.
     * @param array                   $clauseSet      The clause (refinements) to use when selecting the data.
     * @param boolean                 $leftJoin       Should the join be a regular or LEFT join?
     *                                                (if left, it will still copy the rows even when they don't
     *                                                exist in the $refTable).
     * @return self
     */
    protected function runOneFilterQueryIteration(
//        ?TempTable $srcTable, // ie. the current temp-table // @TODO PHP 7.1
        TempTable $srcTable = null, // ie. the current temp-table
        $refTable, // a TempTable or Lookup
        $linkingFields,
        $canTrackFields,
//        ?array $clauseSet, // @TODO PHP 7.1
        array $clauseSet = null,
        bool $leftJoin
    ): self {

        $refTable = $this->resolveRefTable($refTable);

        // create the new temp-table with the desired new fields
        $destTT = $this->newTempTable($canTrackFields, $linkingFields, $refTable);

        // run the query to populate this new temp-table
        $this->runFilterQuery(
            $srcTable,
            $refTable,
            $destTT,
            $linkingFields,
            $canTrackFields,
            $clauseSet,
            $leftJoin
        );

        // update the $currentFilterRef to point to this temp-table
        $this->useTempTable($destTT);

        return $this; // chainable
    }





    /**
     * Create a new Lookup object of the given class, with the necessary name-replacements made
     *
     * @param string $lookupTableClass The Lookup class to instantiate.
     * @return Lookup
     */
    protected function newLookup(string $lookupTableClass): Lookup
    {
        return (new $lookupTableClass($this->resolveStubList()))
            ->setRunQueries($this->getRunQueries())
            ->setQueryTracker($this->getCallerQueryTracker());
    }

    /**
     * Return the current TempTable OBJECT
     *
     * @return TempTable|null
     */
//    protected function curTempTable(): ?TempTable // @TODO PHP 7.1
    protected function curTempTable()
    {
        return ($this->currentFilterRef ? $this->currentFilterRef->curTempTable() : null);
    }

    /**
     * Tell the current filter-ref to add the given TempTable to it's list of temp-tables
     *
     * @param TempTable $tempTable The TempTable to add.
     * @return self
     */
    protected function useTempTable(TempTable $tempTable): self
    {
        if (!$this->currentFilterRef) {
            throw SearchException::noFilterReference();
        }
        $this->currentFilterRef->useTempTable($tempTable);

        return $this; // chainable
    }

    /**
     * Turn the $refTable into an object if needed (and possible)
     *
     * (The given $refTable may already be a TempTable/Lookup object)
     * @param TempTable|Lookup|string|null $refTable The table to use as a reference.
     * @return TempTable|Lookup|string|null
     */
    private function resolveRefTable($refTable)
    {
        // if a Lookup class was passed (which it usually will be), instantiate it now
        if (is_string($refTable)) {
            // it it's a class
            if ((class_exists($refTable)) && (is_subclass_of($refTable, Lookup::class))) {
                $refTable = $this->newLookup($refTable);
                // if it looks like a class that doesn't exist
            } elseif (mb_strpos($refTable, '\\') !== false) {
                throw SearchException::missingRefTableClass($refTable);
            }
        }
        return $refTable;
    }

    /**
     * Return the filterRef for the given $tagName (defaults to the 'allFilters' FilterReference)
     *
     * Used by the Results class.
     * @param string $tagName The tag to get the associated FilterReference for
     * @return FilterReference|null
     */
//    public static function getTaggedFilterRef(string $tagName = null): ?FilterReference // @TODO PHP 7.1
    public function getTaggedFilterRef(string $tagName = null)
    {
        $this->blockIfPending(__FUNCTION__);

        // pick a FilterReference based on the given $tagName
        $tagName = (!is_null($tagName) ? $tagName : $this->allFilterTag);

        if (!isset($this->filterRefTags[$tagName])) {
            throw SearchException::filterTagDoesNotExist($tagName);
        }

        $filterRefHash = $this->filterRefTags[$tagName];
        return $this->filterRefs[$filterRefHash];
    }

    /**
     * Pick the table alias that contains the given field
     *
     * @param array   $tableObjects   The possible TempTable or Lookup that might contain this field.
     * @param string  $fieldName      The field to look for.
     * @param boolean $throwException Should an exception be thrown if the field isn't found?.
     * @return string|null
     */
//    private function pickFieldTableAlias(array $tableObjects, string $fieldName, bool $throwException = true): ?string // @TODO PHP 7.1
    private function pickFieldTableAlias(array $tableObjects, string $fieldName, bool $throwException = true)
    {
        // check if the field belongs to one of the given table objects
        foreach ($tableObjects as $tableAlias => $tableObject) {
            if (($this->isTableObj($tableObject))
                && ($tableObject->hasField($fieldName))) {
                return (string) $tableAlias;
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
            throw ClauseException::fieldNotFound($fieldName, array_keys($tableObjects)); // @todo is this the right exception class to use here?
        }
        return null;
    }

    /**
     * Add the given $linkingFields to the $canTrackFields fields
     *
     * @param string|array|null       $extraTrackFields The fields to add to.
     * @param string|array            $linkingFields    The fields to add.
     * @param TempTable|Lookup|string $refTable         The ref-table (usually an object which knows details
     *                                                  about that table).
     * @return array
     */
    private function buildCanTrackFields($extraTrackFields, $linkingFields, $refTable = null): array
    {
        $extraTrackFields = $this->buildAssocArray($extraTrackFields);
        $linkingFields = $this->buildAssocArray($linkingFields);
        $refTable = $this->resolveRefTable($refTable); // turn the $refTable into an abject if needed (and possible)

        $canTrackFields = [];
        // put the $linkingFields fields at the beginning
        foreach ($linkingFields as $index => $value) {
            $canTrackFields[$index] = $value;
        }
        // add the original $extraTrackFields
        foreach ($extraTrackFields as $index => $value) {
            $canTrackFields[$index] = $value;
        }
        // add the fields that the ref-table contains
        if ((is_object($refTable)) && ($this->isTableObj($refTable))) {
            foreach ($refTable->getFieldNames() as $field) {
                if (!isset($canTrackFields[$field])) {
                    $canTrackFields[$field] = $field;
                }
            }
        }

        // remove fields where the definition is empty (it may be set to use a clause that wasn't created)
        return array_filter($canTrackFields);
    }





    /**
     * Take the given lists of fields and return a list containing only the ones that should be tracked.
     *
     * @param array|string ...$args Array of $canTrackFields field-name arrays to check through.
     * @return array
     */
    private function pickFieldsToTrack(...$args): array
    {
        $fieldsToTrack = [];
        foreach ($args as $canTrackFields) {

            $canTrackFields = $this->buildAssocArray($canTrackFields);
            foreach ($canTrackFields as $fieldName => $expression) {
                if ($this->willTrackField((string) $fieldName)) {
                    $fieldsToTrack[$fieldName] = $expression;
                }
            }
        }
        return $fieldsToTrack;
    }

    /**
     * It we want to track the given field
     *
     * This doesn't care if it's already being tracked or not.
     * @param string $fieldName The field-name to check.
     * @return boolean
     */
    protected function willTrackField(string $fieldName): bool
    {
        return in_array($fieldName, $this->resolvedFieldsToTrack);
    }

    /**
     * Check whether we want to track the given $fieldName, AND HAVEN'T TRACKED IT YET
     *
     * This checks with the CURRENT filter-ref to see if it's currently tracking it
     * @param string $fieldName The field name to check.
     * @return boolean
     */
    protected function needToTrackField(string $fieldName): bool
    {
        if ($this->willTrackField($fieldName)) {
            ($this->currentFilterRef ? $this->currentFilterRef->hasField($fieldName) : false);
        }
        return false;
    }

    /**
     * Take the given order-by fields/aliases and return the fields needed
     *
     * @param array $orderBy The order-by fields/aliases to get fields for.
     * @return array
     */
    private function getOrderByFields(array $orderBy): array
    {
        $fieldNames = [];
        foreach (array_keys($orderBy) as $name) {

            // check if this is simply a field
            if (isset($this->resolvedFieldDefinitions[$name])) {
                $fieldNames[] = $name;
            // otherwise check if this is an order-by alias
            } elseif (isset($this->orderByAliases[$name])) {
                foreach ($this->orderByAliases[$name] as $orderByClause) {
                    $fields = $orderByClause['fields'];
                    $fields = (is_array($fields) ? $fields : [$fields]);
                    $fieldNames = array_merge($fieldNames, $fields);
                }
            } else {
                throw SearchException::invalidOrderBy($name);
            }
        }
        return array_unique($fieldNames);
    }





    /**
     * Create a Results object - used to retrieve the results from a search
     *
     * @param string $tagName The tag to start off with (the 'allFilters' tag will be used by default.
     * @return Results
     */
    public function results(string $tagName = null): Results
    {
        $this->blockIfPending(__FUNCTION__);

        return (new Results($this))
            ->setRunQueries($this->getRunQueries())
            ->setQueryTracker($this->getCallerQueryTracker())
            ->orderBy($this->getCallerOrderBy())
            ->resetOrderByNextTime();
    }
}
