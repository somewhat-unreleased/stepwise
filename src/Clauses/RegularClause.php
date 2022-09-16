<?php

namespace CodeDistortion\Stepwise\Clauses;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use CodeDistortion\Stepwise\Exceptions\ClauseException;
use CodeDistortion\Stepwise\Internal\Clause;

/**
 * Represents a simple database clause, used when building a query
 *
 * eg.
 * (`price` >= 10)
 * (`age` BETWEEN 20 AND 30)
 * (`category_id` IN (5, 6, 7))
 */
class RegularClause extends Clause
{
    /**
     * The field name this clause is about
     *
     * @var string
     */
    protected $fieldName;

    /**
     * The operator to be applied
     *
     * @var string|null
     */
    protected $operator;

    /**
     * The values to use in comparison
     *
     * @var array
     */
    protected $values;



    /**
     * Constructor
     *
     * @param string $fieldName The field name this clause is about.
     * @param string $operator  The operator to be applied ('<', '<=', '=', '>=', '>', '!=', 'BETWEEN', etc)
     * @param mixed  $values    The values to use in comparison.
     */
    public function __construct(string $fieldName, string $operator, $values)
    {
        $operator = mb_strtoupper($operator);
        if ((is_array($values)) && (!in_array($operator, ['=', 'BETWEEN']))) {
            throw ClauseException::operatorMustBeEqualsForArray();
        }
        if (($operator == 'BETWEEN') && (count($values) != 2)) {
            throw ClauseException::valuesMustBeArrayForBetween();
        }

        // make sure the values are unique
        $values = (is_array($values) ? $values : [$values]);
        $this->values = [];
        foreach ($values as $value) {
            if (!in_array($value, $this->values, true)) {
                $this->values[] = $value;
            }
        }

        // if the BETWEEN start and end values are the same,
        // change the operator to check if values are equal to that value
        if (($operator == 'BETWEEN') && (count($this->values) == 1)) {
            $operator = '=';
        }

        $this->fieldName = $fieldName;
        $this->operator = $operator;
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

            $values = $this->values;
            $hasNull = false;
            if (!in_array($this->operator, ['BETWEEN'])) {
                if ($hasNull = (in_array(null, $values, true))) {
                    $index = array_search(null, $values, true);
                    unset($values[$index]);
                }
            }

            $clauses = [];
            if (count($values) == 1) {
                $clauses[] = "`".$tableAlias."`.`".$this->fieldName."` ".$this->operator." ?";
                $queryValues[] = reset($values);
            } elseif (count($values) > 1) {
                $clauses[] = ($this->operator == 'BETWEEN'
                    ? "`".$tableAlias."`"
                        .".`".$this->fieldName."` "
                        ."BETWEEN ? AND ?" // only two values here, checked above
                    : "`".$tableAlias."`"
                        .".`".$this->fieldName."` "
                        ."IN (".implode(', ', array_fill(0, count($values), '?')).")"
                );
                $queryValues = array_merge($queryValues, $values);
            }

            if ($hasNull) {
                $clauses[] = "`".$tableAlias."`.`".$this->fieldName."` "
                    .($this->operator == '=' ? "IS NULL" : "IS NOT NULL");
            }

            return "(".implode(($this->operator == '=' ? ' OR ' : ' AND '), $clauses).")";
        }
        return null;
    }

    /**
     * Build an array of clause criteria for min and max values (both optional)
     *
     * Handles when Carbon objects are passed to $min, $max, $lowestMin, $highestMax.
     * @param string                                    $fieldName  The field name the new clause is about.
     * @param integer|float|Carbon|CarbonImmutable|null $min        The minimum bound to compare to.
     * @param integer|float|Carbon|CarbonImmutable|null $max        The maximum bound to compare to.
     * @param integer|float|Carbon|CarbonImmutable|null $lowestMin  The lowest the minimum value can be.
     * @param integer|float|Carbon|CarbonImmutable|null $highestMax The highest the minimum value can be.
     * @return self|null
     */
//    public static function buildRange(string $fieldName, $min, $max, $lowestMin = null, $highestMax = null): ?self // @TODO PHP 7.1
    public static function buildRange(string $fieldName, $min, $max, $lowestMin = null, $highestMax = null)
    {
        // allow carbon objects to be compared as integers (needed by min() and max() below)
        $minNum= (($min instanceof Carbon) || ($min instanceof CarbonImmutable)
            ? $min->timestamp
            : $min);
        $maxNum= (($max instanceof Carbon) || ($max instanceof CarbonImmutable)
            ? $max->timestamp
            : $max);
        $lowestMinNum = (($lowestMin instanceof Carbon) || ($lowestMin instanceof CarbonImmutable)
            ? $lowestMin->timestamp
            : $lowestMin);
        $highestMaxNum = (($highestMax instanceof Carbon) || ($highestMax instanceof CarbonImmutable)
            ? $highestMax->timestamp
            : $highestMax);

        foreach ([
            'min' => $minNum,
            'max' => $maxNum,
            'lowestMin' => $lowestMinNum,
            'highestMax' => $highestMaxNum
        ] as $name => $num) {
            if ((!is_null($num)) && (!is_int($num))) {
                if (!is_float($num)) { // separated for phpstan (???)
                    throw ClauseException::invalidRangeValueType($name);
                }
            }
        }

        // record the original values so they can be substituted back in after the min/max comparisons have been made
        $origValues = [];
        $origValues[$minNum]        = $min;
        $origValues[$maxNum]        = $max;
        $origValues[$lowestMinNum]  = $lowestMin;
        $origValues[$highestMaxNum] = $highestMax;

        if (!is_null($minNum)) {
            // make sure $minNum value is between $lowestMinNum and $highestMaxNum
            $minNum = (!is_null($lowestMinNum) ? max($lowestMinNum, $minNum) : $minNum);
            // make sure $minNum value is between $lowestMinNum and $highestMaxNum
            $minNum = (!is_null($highestMaxNum) ? min($highestMaxNum, $minNum) : $minNum);
        }
        if (!is_null($maxNum)) {
            // make sure $maxNum value is between $lowestMinNum and $highestMaxNum
            $maxNum = (!is_null($lowestMinNum) ? max($lowestMinNum, $maxNum) : $maxNum);
            // make sure $maxNum value is between $lowestMinNum and $highestMaxNum
            $maxNum = (!is_null($highestMaxNum) ? min($highestMaxNum, $maxNum) : $maxNum);

            // swap the range values if they're in the wrong order
            if ((!is_null($minNum)) && ($maxNum < $minNum)) {
                $temp = $maxNum;
                $maxNum = $minNum;
                $minNum = $temp;
            }
        }

        if ((!is_null($minNum)) && ($minNum === $maxNum)) {
            // where min and max values are the same
            return new static($fieldName, '=', $origValues[$minNum]);
        }
        if ((!is_null($minNum)) && (!is_null($maxNum))) {
            return new static($fieldName, 'BETWEEN', [$origValues[$minNum], $origValues[$maxNum]]);
        }
        if (!is_null($minNum)) {
            return new static($fieldName, '>=', $origValues[$minNum]);
        }
        if (!is_null($maxNum)) {
            return new static($fieldName, '<=', $origValues[$maxNum]);
        }
        return null;
    }

    /**
     * Take the given array of values and create an array of clauses one for each value.
     *
     * ie. Split the values up into multiple clauses.
     * (The idea is that each one is used in a separate refinement iteration).
     * @param string $fieldName The field name the new clauses are about.
     * @param array  $values    The values to build the clauses from.
     * @return array
     */
    public static function buildClauseSets(string $fieldName, array $values): array
    {
        $clauseSets = [];
        foreach ($values as $value) {
            $clauseSets[] = [new static($fieldName, '=', $value)];
        }
        return $clauseSets;
    }
}
