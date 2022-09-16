<?php

namespace CodeDistortion\Stepwise\Internal;

use DB;

/**
 * Methods used to clean-up after the Stepwise object
 */
trait StepwiseCleanUp
{

    /**
     * Remove the temp tables that were created during the search process
     *
     * @return self
     */
    public function dropTempTables(): self
    {
        // find all the TempTable objects
        $tempTables = [];
        foreach ($this->filterRefs as $filterRef) {
            $tempTables = array_merge($tempTables, $filterRef->getTempTables());
        }

        // find all the temp db tables that exist
        $tableNames = [];
        foreach ($tempTables as $tempTable) {
            if ($tempTable->existsInDB()) {
                $tableNames[] = $tempTable->getTableName();
                $tempTable->setExistsInDB(false);
            }
        }

        // remove these temp tables
        if (count($tableNames)) {
            $query = "DROP TEMPORARY TABLE IF EXISTS `".implode("`, `", $tableNames)."`";

            if ($this->getRunQueries()) {
                DB::statement($query);
            }
            if ($this->getCallerQueryTracker()) {
                $this->getCallerQueryTracker()->trackQuery($query); // used by testing code
            }
        }

        return $this; // chainable
    }
}
