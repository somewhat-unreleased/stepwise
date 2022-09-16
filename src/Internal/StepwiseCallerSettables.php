<?php

namespace CodeDistortion\Stepwise\Internal;

use CodeDistortion\Stepwise\Exceptions\SettingsException;
use CodeDistortion\Stepwise\Input;

/**
 * These are the Stepwise search things that happen before a search has occurred
 */
trait StepwiseCallerSettables
{
    /**
     * Do extra things to help with testing
     *
     * @var boolean
     */
    private $callerTestMode = false;

    /**
     * Should the queries actually be run?
     *
     * @var boolean
     */
    private $callerRunQueries = true;

    /**
     * Keeps track of the queries that were generated (used by testing code)
     *
     * @var QueryTracker|null
     */
    private $callerQueryTracker = null;

    /**
     * A store for key-value pairs set by the caller which can be read in conjunction with the Input object by filter
     * methods
     *
     * @var array
     */
    private $callerValues = [];





    /**
     * Lookup-table-name stub replacements that the caller has specified
     *
     * (if not found in this array, they will be resolved using the $this->stubConfigKeys)
     * @var array
     */
    private $callerStubReplacements = [];

    /**
     * Internal cache - The resolved list of lookup-table-name stubs
     *
     * (null means not resolved yet)
     * @var array|null
     */
    private $resolvedStubReplacements = null;

    /**
     * The search input to use when running the search
     *
     * @var Input|null
     */
    private $callerInput = null;

    /**
     * The primary key fields to be used when performing a search
     *
     * This will fall-back to $this->defaultPrimaryKeyFields if not specified.
     * No primary key (an empty array) is allowed.
     * @var array|null
     */
    private $callerPrimaryKey = null;

    /**
     * The fields to pick up along the way when performing the search
     *
     * @var array
     */
    private $callerFieldsToTrack = [];

    /**
     * The order-by fields/aliases (these will be used to track the relevant fields)
     *
     * @var array
     */
    private $callerOrderBy = [];

    /**
     * The filters to include/exclude when picking temp-tables to tag.
     *
     * @var array
     */
    private $callerTags = [];





    /**
     * Turn test-mode on - which does extra things for testing code to check
     *
     * @param boolean $testMode Turn the setting on or off.
     * @return self
     */
    public function testMode(bool $testMode = true): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerTestMode = $testMode;

        return $this; // chainable
    }

    /**
     * Internal getter for the callerTestMode - Returns whether test-mode is on or off
     *
     * @return boolean
     */
    private function getTestMode(): bool
    {
        return $this->callerTestMode;
    }



    /**
     * Should queries actually be run?
     *
     * @param boolean $runQueries Turn the setting on or off.
     * @return self
     */
    public function setRunQueries(bool $runQueries): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerRunQueries = $runQueries;

        return $this; // chainable
    }

    /**
     * Internal getter for runQueries
     *
     * @return boolean
     */
    private function getRunQueries(): bool
    {
        return $this->callerRunQueries;
    }



    /**
     * Let the caller pass a queryTracker to track queries with
     *
     * @param QueryTracker $queryTracker The tracker to track queries with.
     * @return self
     */
//    public function setQueryTracker(?QueryTracker $queryTracker): self // @TODO PHP 7.1
    public function setQueryTracker(QueryTracker $queryTracker = null)
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerQueryTracker = $queryTracker;

        return $this; // chainable
    }

    /**
     * Internal getter for queryTracker
     *
     * @return QueryTracker|null
     */
//    private function getQueryTracker(): ?QueryTracker // @TODO PHP 7.1
    private function getCallerQueryTracker()
    {
        return $this->callerQueryTracker;
    }





    /**
     * Let the caller pass a key-value-pair, and store it for later
     *
     * Useful to pass values that aren't part of the filter-input. eg. the user's id or the current website id
     * @param string $name  The name of the value to store.
     * @param mixed  $value The value to store.
     *
     * @return self
     */
    public function setValue(string $name, $value): self
    {
        return $this->setValues([$name => $value]);
    }

    /**
     * Let the caller pass an array of key-value-pairs, and store them for later
     *
     * Useful to pass values that aren't part of the filter-input. eg. the user's id or the current website id
     * @param array $values An key-value-pair array of values to store.
     * @return self
     */
    public function setValues(array $values): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerValues = array_merge($this->callerValues, $values);

        return $this; // chainable
    }

    /**
     * Let the caller reset the key-value-pair values
     *
     * @return self
     */
    public function resetValues(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerValues = [];

        return $this; // chainable
    }

    /**
     * Let the caller retrieve a previously set value.
     *
     * @param string $name The name of the value to retrieve.
     * @return mixed
     */
    protected function getValue(string $name)
    {
        if (array_key_exists($name, $this->callerValues)) {
            return $this->callerValues[$name];
        }
        return null;
    }





    /**
     * Lets the caller specify a replacement for a lookup-table name stub
     *
     * @param string $name        The name of the stub being replaced.
     * @param string $replacement The replacement value.
     * @return self
     */
    public function stubReplacement(string $name, string $replacement): self
    {
        return $this->stubReplacements([$name => $replacement]);
    }

    /**
     * Lets the caller specify replacements for lookup-table name stubs
     *
     * @param array $replacements A key-value-pair of stubs and their replacements.
     * @return self
     */
    public function stubReplacements(array $replacements): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerStubReplacements = array_merge($this->callerStubReplacements, $replacements);
        $this->resolvedStubReplacements = null; // force them to be resolved again when resolveStubList() is called

        return $this; // chainable
    }

    /**
     * Lets the caller reset the lookup-table stub replacements
     *
     * @return self
     */
    public function resetStubReplacements(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerStubReplacements = [];
        $this->resolvedStubReplacements = null; // force them to be resolved again when resolveStubList() is called

        return $this; // chainable
    }

    /**
     * Build the complete list of stubs - resolving any from the Laravel config if needed
     *
     * @return array
     */
    private function resolveStubList(): array
    {
        // use the previously resolved list
        if (!is_null($this->resolvedStubReplacements)) {
            return $this->resolvedStubReplacements;
        }

        $stubReplacements = $this->callerStubReplacements;

        // resolve outstanding ones
        foreach ($this->stubConfigKeys as $name => $configKey) {

            if (!array_key_exists($name, $stubReplacements)) {
                $replacement = config($configKey);
                if ($replacement) {
                    $stubReplacements[$name] = $replacement;
                } else {
                    throw SettingsException::unresolvableStub($name, $configKey);
                }
            }
        }

        $this->resolvedStubReplacements = $stubReplacements;
        return $stubReplacements;
    }



    /**
     * Let the caller specify input values for the search
     *
     * @param Input|array $searchInput The input values to use when searching.
     * @return self
     */
    public function input($searchInput): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        // if it's an array, add it to the existing Input (or create a new one)
        if (is_array($searchInput)) {

            // create a new Input the first time
            if (is_null($this->callerInput)) {
                $class = $this->inputClass;
                $this->callerInput = new $class($searchInput);
            // thereafter just add the input
            } else {
                $this->callerInput->add($searchInput);
            }

        // otherwise make sure it's the correct type of Input object
        } elseif ($searchInput instanceof $this->inputClass) {
            $this->callerInput = $searchInput;
        } else {
            throw SettingsException::invalidInputClass($searchInput, $this->inputClass);
        }

        return $this; // chainable
    }

    /**
     * Reset the search Input
     *
     * @return self
     */
    public function resetInput(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerInput = null;

        return $this; // chainable
    }

    /**
     * Internal getter for the callerInput
     *
     * @return Input|null
     */
//    private function getInput(): ?Input // @TODO PHP 7.1
    private function getCallerInput()
    {
        return $this->callerInput;
    }





    /**
     * Let the caller specify the primary key fields to use when performing a search
     *
     * No primary key (an empty array) is allowed.
     * @param array $primaryKey The fields to use as the primary key.
     * @return self
     */
    public function primaryKey(array $primaryKey): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerPrimaryKey = $primaryKey;

        return $this; // chainable
    }

    /**
     * Internal getter for the callerPrimaryKey
     *
     * @return array|null
     */
//   private function getPrimaryKey(): ?array // @TODO PHP 7.1
   private function getCallerPrimaryKey()
    {
        return $this->callerPrimaryKey;
    }





    /**
     * Let the caller specify a field to pick up along the way when performing the search
     *
     * @param string $fieldToTrack A field to pick up along the way.
     * @return self
     */
    public function trackField(string $fieldToTrack): self
    {
        return $this->trackFields([$fieldToTrack]);
    }

    /**
     * Let the caller specify the fields to pick up along the way when performing the search
     *
     * @param array $fieldsToTrack The fields to pick up along the way.
     * @return self
     */
    public function trackFields(array $fieldsToTrack): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerFieldsToTrack = array_unique(array_merge($this->callerFieldsToTrack, $fieldsToTrack));

        return $this; // chainable
    }

    /**
     * Let the caller reset the list of fields to track
     *
     * @return self
     */
    public function resetTrackFields(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerFieldsToTrack = [];

        return $this; // chainable
    }

    /**
     * Internal getter for the callerFieldsToTrack
     *
     * @return array
     */
    private function getCallerFieldsToTrack(): array
    {
        return $this->callerFieldsToTrack;
    }





    /**
     * Let the caller specify the intended order-by fields/aliases (these will be used to track the relevant fields)
     *
     * @param array|string   $name      The name of the field/alias to use OR an array of order-by fields/aliases to
     *                                  use.
     * @param string|boolean $direction The direction to order by.
     *
     * @return self
     */
    public function orderBy($name, string $direction = 'ASC'): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        // handle when called singularly
        if (is_string($name)) {
            return $this->orderBy([$name => $direction]);
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

        $this->callerOrderBy = array_merge($this->callerOrderBy, $orderBy);

        return $this; // chainable
    }

    /**
     * Let the caller reset the intended order-by fields/aliases
     *
     * @return self
     */
    public function resetOrderBy(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerOrderBy = [];

        return $this; // chainable
    }

    /**
     * Internal getter for the callerOrderBy
     *
     * @return array
     */
    private function getCallerOrderBy(): array
    {
        return $this->callerOrderBy;
    }





    /**
     * Let the caller specify a particular set of filters to tag
     *
     * @param string $tagName The tag to create.
     * @param array $filters  An array of filters or filter-aliases (with preceding '+' or '-' to include or exclude
     *                        them)
     * @return self
     */
    public function tag(string $tagName, array $filters): self
    {
        return $this->tags([$tagName => $filters]);
    }

    /**
     * Let the caller specify the filters to include/exclude when picking temp-tables to tag
     *
     * @param array $tags The filters to include/exclude when tagging.
     * @return self
     */
    public function tags(array $tags): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerTags = array_merge($this->callerTags, $tags);

        return $this; // chainable
    }

    /**
     * Let the caller reset the tag-list
     *
     * @return self
     */
    public function resetTags(): self
    {
        $this->blockIfNotPending(__FUNCTION__);

        $this->callerTags = [];

        return $this; // chainable
    }

    /**
     * Internal getter for the callerTags
     *
     * @return array
     */
    private function getCallerTags(): array
    {
        return $this->callerTags;
    }
}
