<?php

namespace CodeDistortion\Stepwise\Clauses;

use CodeDistortion\Stepwise\Internal\Clause;

/**
 * Represents a simple database clause, used when building a query
 *
 * eg.
 * `product_text` LIKE '%cheese%'
 */
class LikeClause extends Clause
{
    /**
     * The field-names to use in the clause
     *
     * @var string
     */
    protected $fieldName;

    /**
     * The LIKE value in the clause
     *
     * @var string|null
     */
    protected $like;



    /**
     * Constructor
     *
     * @param string      $fieldName The field-name/s to use in the clause.
     * @param string|null $like      The LIKE value in the clause.
     */
//    public function __construct(string $fieldName, ?string $like) // @TODO PHP 7.1
    public function __construct(string $fieldName, string $like = null)
    {
        $this->fieldName = $fieldName;
        $this->like = $like;
    }

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
//    public function render(array $tableObjects, array &$queryValues): ?string // @TODO PHP 7.1
    public function render(array $tableObjects, array &$queryValues)
    {
        // work out which table the field is from
        if ($tableAlias = $this->pickFieldTableAlias($tableObjects, $this->fieldName, false)) {
            $queryValues[] = $this->like;
            return "`".$tableAlias."`.`".$this->fieldName."` LIKE ?";
        }
        return null;
    }

    /**
     * Escape the given string ready to be used in a LIKE clause
     *
     * @param string|integer|float|null $value The value to escape.
     * @return string|null
     */
//    public static function esc($value): ?string // @TODO PHP 7.1
    public static function esc($value)
    {
        if (is_null($value)) {
            return null;
        }
        return str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], (string) $value);
    }
}
