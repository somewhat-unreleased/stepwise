<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Exceptions\InputException;
use Exception;

/**
 * Represents the values to apply to a Stepwise search
 */
abstract class Input
{
    /**
     * The values this object currently represents
     *
     * @var array
     */
    protected $values = [];

    /**
     * The default values of this object's fields
     *
     * @var array
     */
    protected $defaults = [];



    /**
     * Constructor
     *
     * @param array $values The values to store.
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $index => $value) {
            $this->values[$index] = $value;
        }
    }

    /**
     * Magic method to return a value stored in this object
     *
     * @param string $name The name of the value to retrieve.
     * @return mixed
     * @throws Exception Thrown when the requested value doesn't exist.
     */
    public function __get(string $name)
    {
        // get the value if it's been set
        if ((array_key_exists($name, $this->values)) && (!is_null($this->values[$name]))) {
            return $this->values[$name];
        }
        // otherwise fall back to it's default
        if (array_key_exists($name, $this->defaults)) {
            return $this->defaults[$name];
        }
return null; // @todo
        throw InputException::undefinedProperty($name, $class);
    }

    /**
     * Magic method to store a value in this object
     *
     * @param string $name The name of the value to set.
     * @param mixed $name The value to set.
     * @return void
     */
//    public function __set(string $name, $value): void // @TODO PHP 7.1
    public function __set(string $name, $value)
    {
        $this->values[$name] = $value;
    }

    /**
     * Add an array of key-value-pairs to this object
     *
     * @param array $values Key-value-pair values to add.
     * @return self
     */
    public function add(array $values): self
    {
        $this->values = array_merge($this->values, $values);

        return $this; // chainable
    }

    /**
     * Remove a value from this object (a default value may still exist)
     *
     * @param string $name The name of the value to unset.
     * @return self
     */
    public function unset(string $name): self
    {
        unset($this->values[$name]);

        return $this; // chainable
    }

    /**
     * Returns whether the search should proceed or not
     *
     * @return boolean
     */
    public function searchCanProceed(): bool
    {
        return true;
    }
}
