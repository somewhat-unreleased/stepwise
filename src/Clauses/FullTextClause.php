<?php

namespace CodeDistortion\Stepwise\Clauses;

use CodeDistortion\Stepwise\Internal\Clause;

/**
 * Represents a simple database clause, used when building a query
 *
 * eg.
 * (MATCH(`name`, `description`) AGAINST(+searchTerm IN BOOLEAN MODE))
 */
class FullTextClause extends Clause
{
    /**
     * The field-names to use in the clause
     *
     * @var array
     */
    protected $fieldNames;

    /**
     * The AGAINST part of the clause
     *
     * @var string
     */
    protected $against;

    /**
     * Whether BOOLEAN MODE is to be used
     *
     * @var boolean
     */
    protected $inBooleanMode;



    /**
     * Constructor
     *
     * @param string|array $fieldNames    The field-name/s to use in the clause.
     * @param string       $against       The AGAINST part of the clause.
     * @param boolean      $inBooleanMode Whether BOOLEAN MODE is to be used.
     */
    public function __construct($fieldNames, string $against, bool $inBooleanMode = false)
    {
        $this->fieldNames = (is_array($fieldNames) ? $fieldNames : [$fieldNames]);
        $this->against = $against;
        $this->inBooleanMode = $inBooleanMode;
    }

//    /**
//     * Return the field's name
//     *
//     * @return string
//     */
//     public function getFieldName(): string
//     {
//         return reset($this->fieldNames);
//     }

    /**
     * Build the clause as a string
     *
     * @param array $tableObjects The possible TempTable or Lookup that might contain this field.
     * @param array $queryValues  The list of query values is built up in this array reference.
     * @return string|null
     */
//    public function render(array $tableObjects, array &$queryValues): ?string // @TODO PHP 7.1
    public function render(array $tableObjects, array &$queryValues)
    {
        // work out which table each field is from
        $fieldNames = [];
        foreach ($this->fieldNames as $fieldName) {
            if ($tableAlias = $this->pickFieldTableAlias($tableObjects, $fieldName, false)) {
                $fieldNames[] = "`".$tableAlias."`.`".$fieldName."`";
            }
        }

        // put the clause together
        if (count($fieldNames)) {
            $queryValues[] = $this->against;
            return "MATCH(".implode(", ", $fieldNames).") "
                ."AGAINST(?".($this->inBooleanMode ? " IN BOOLEAN MODE" : "").")";
        }
        return null;
    }
}
