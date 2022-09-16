<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\SearchException;
use CodeDistortion\Stepwise\Lookup;

trait StepwiseTools
{
    /**
     * Throw an exception if the search HAS already commenced
     *
     * @param string $method The method that was attempted.
     * @return boolean
     */
    protected function blockIfNotPending(string $method): bool
    {
        if (!$this->searchIsPending) {
            throw SearchException::searchIsNotPending($method);
        }
        return true;
    }

    /**
     * Throw an exception if the search has NOT commenced yet
     *
     * @param string $method The method that was attempted.
     * @return boolean
     */
    protected function blockIfPending(string $method): bool
    {
        if ($this->searchIsPending) {
            throw SearchException::searchIsPending($method);
        }
        return true;
    }

    /**
     * Throw an exception if not running in test mode
     *
     * @param string $method The method that was attempted.
     * @return boolean
     */
    protected function blockIfNotTesting(string $method): bool
    {
        if (!$this->getTestMode()) {
            throw SearchException::notInTestMode($method);
        }
        return true;
    }



    /**
     * Check whether the given variable is a "table" object
     *
     * @param string|Lookup|TempTable|null $tableObject The variable to check.
     *
     * @return boolean
     */
    private function isTableObj($tableObject): bool
    {
        return (($tableObject instanceof Lookup) || ($tableObject instanceof TempTable));
    }

    /**
     * Take the given value and turn it into an associative array
     *
     * @param array|integer|float|boolean|string|null $values The value to turn into an associative array.
     * @return array
     */
    private function buildAssocArray($values): array
    {
        $values = ($values === '' ? null : $values); // don't use empty strings
        $values = (is_array($values) ? $values : array_filter([$values]));
        $newValues = [];
        foreach ($values as $index => $value) {
            $index = (!is_int($index) ? $index : $value);
            $newValues[$index] = $value;
        }
        return $newValues;
    }

    /**
     * Remove $values from the given $array
     *
     * @param array   $array  The source array to alter.
     * @param mixed   $values The values to remove from the source array.
     * @param boolean $strict Perform comparisons strictly?.
     * @return array
     */
    private function removeByValue(array $array, $values, bool $strict = false): array
    {
        $values = (is_array($values) ? $values : [$values]);
        foreach ($values as $value) {
            if (($index = array_search($value, $array, $strict)) !== false) {
                unset($array[$index]);
            }
        }
        return $array;
    }
}
