<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Exceptions\SettingsException;
use CodeDistortion\Stepwise\Internal\Helper;

/**
 * Contains settings and logic common to other StepwiseToDelete classes
 */
abstract class StepwiseToDelete
{
    /**
     * The PreCacher class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $preCacherClass;

    /**
     * The Input class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $inputClass;

    /**
     * The Stepwise class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $searcherClass;

    /**
     * The Lookup classes used by this StepwiseToDelete
     *
     * @var array
     */
    protected static $lookupClasses = [];

    /**
     * The Laravel config keys for each Lookup name stub
     *
     * @var array
     */
    protected static $stubReplacementConfigKeys = [];

    /**
     * The possible fields that can be used
     *
     * @var array
     */
    protected static $fieldDefinitions = [];





    /**
     * Build a new PreCacher object
     *
     * @return PreCacher
     * @throws SettingsException Thrown when the PreCacher class hasn't been set up correctly.
     */
    public function newPreCacher(): PreCacher
    {
        Helper::checkClass('PreCacher', static::$preCacherClass, PreCacher::class);
        return new static::$preCacherClass();
    }

    /**
     * Build a new Input object
     *
     * @return Input
     * @throws SettingsException Thrown when the Input class hasn't been set up correctly.
     */
    public function newInput(): Input
    {
        Helper::checkClass('Input', static::$inputClass, Input::class);
        return new static::$inputClass();
    }

    /**
     * Build a new Stepwise object
     *
     * @return Stepwise
     * @throws SettingsException Thrown when the Stepwise class hasn't been set up correctly.
     */
    public function newSearch(): Stepwise
    {
        Helper::checkClass('Stepwise', static::$searchClass, Stepwise::class);
        return new static::$searcherClass();
    }





    /**
     * Return the stubReplacementConfigKeys that are used
     *
     * Used by the ???.
     * @return array
     */
    public static function getStubReplacementConfigKeys(): array
    {
        return static::$stubReplacementConfigKeys;
    }

    /**
     * Return the Lookups that are used
     *
     * Used by the PreCacher.
     * @return array
     */
    public static function getLookupClasses(): array
    {
        return static::$lookupClasses;
    }

    /**
     * Return the Input class used
     *
     * Used by the ???.
     * @return string
     */
    public static function getSearchInputClass(): string
    {
        return static::$inputClass;
    }

    /**
     * Return the field definitions held in this class
     *
     * Used by the Lookup.
     * @return array
     */
    public static function getFieldDefinitions(): array
    {
        return static::$fieldDefinitions;
    }















    /**
     * Test that this class has been set up properly
     *
     * @return void
     * @throws SettingsException Thrown when something has been set up incorrectly.
     */
//    public function selfTest(): void // @TODO PHP 7.1
    public static function selfTest()
    {
        Helper::checkClass('PreCacher', static::$preCacherClass, PreCacher::class);
        Helper::checkClass('Input', static::$inputClass, Input::class);
        Helper::checkClass('Stepwise', static::$searchClass, Stepwise::class);

        // Lookup classes
        if (!count(static::$lookupClasses)) {
            throw SettingsException::noLookupClasses();
        }
        foreach (static::$lookupClasses as $lookupClass) {
            Helper::checkClass('Lookup', $lookupClass, Lookup::class);
        }

        // check that the fields are all ok!
        static::resolveFieldDefinitions();
    }

    /**
     * Check that the fields used by this class are all defined and set up properly
     *
     * @return boolean
     * @throws SettingsException Thrown when something is wrong with the field definitions.
     */
    private static function resolveFieldDefinitions(): bool
    {
        foreach (static::$lookupClasses as $lookupClass) {
            foreach ($lookupClass::getFieldDefinitions() as $field => $definition) {

                // if it exists in this object too, check that it matches
                if (isset(static::$fieldDefinitions[$field])) {
                    if ($definition !== static::$fieldDefinitions[$field]) {
                        throw SettingsException::conflictingFieldDefinition(
                            $field,
                            static::$fieldDefinitions[$field],
                            $definition,
                            $lookupClass
                        );
                    }
                    // if it's new, save the definition
                } else {
                    static::$fieldDefinitions[$field] = $definition;
                }
            }
        }
        return true;
    }
}
