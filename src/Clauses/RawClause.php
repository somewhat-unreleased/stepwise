<?php

namespace CodeDistortion\Stepwise\Clauses;

use CodeDistortion\Stepwise\Internal\Clause;

/**
 * Represents a raw database clause, used when building a query
 *
 * eg.
 * null
 * or
 * RAND()
 */
class RawClause extends Clause
{

    /**
     * The RAW text to use in the clause
     *
     * @var string|integer|float|null
     */
    protected $raw;



    /**
     * Constructor
     *
     * @param string|integer|float|null $raw The RAW text to use in the clause.
     */
    public function __construct($raw)
    {
        $this->raw = $raw;
    }

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
        return (is_null($this->raw) ? 'NULL' : (string) $this->raw);
    }
}
