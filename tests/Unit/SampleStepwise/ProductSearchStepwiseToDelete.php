<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise;

use CodeDistortion\Stepwise\StepwiseToDelete;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductAllergenLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductMakerLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductPriceLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductTextLookup;

class ProductSearchStepwiseToDelete extends StepwiseToDelete
{
    /**
     * The PreCacher class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $preCacherClass = ProductSearchPreCacher::class;

    /**
     * The Input class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $inputClass = ProductSearchInput::class;

    /**
     * The Stepwise class used by this StepwiseToDelete
     *
     * @var string
     */
    protected static $searchClass = ProductSearchStepwise::class;

    /**
     * The Lookup classes used by this StepwiseToDelete
     *
     * @var array
     */
    protected static $lookupClasses = [
        ProductAllergenLookup::class,
        ProductMakerLookup::class,
        ProductPriceLookup::class,
        ProductTextLookup::class,
    ];

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
    protected static $fieldDefinitions = [
        'product_id'                    => "BIGINT(20) UNSIGNED NOT NULL",
        'maker_id'                      => "BIGINT(20) UNSIGNED NOT NULL",
//        'product_cat_id'                => "INT(10) UNSIGNED NOT NULL",
        'product_allergen_id'           => "SMALLINT(6) UNSIGNED NOT NULL",
//        'product_name'                  => "VARCHAR(32) NOT NULL",
//        'product_description'           => "TEXT NULL",
        'product_price'                 => "DECIMAL(18, 3) UNSIGNED NOT NULL",
//        'product_score'                 => "DECIMAL(4, 5) UNSIGNED NOT NULL",
//        'product_created_at_utc'        => "TIMESTAMP NULL DEFAULT NULL", // *
//        'product_search_term_relevance' => "DECIMAL(20, 15) NOT NULL",
        'product_random_score'          => "DECIMAL(17, 16) NOT NULL",
//        'product_text'                  => "VARCHAR(255) NULL DEFAULT NULL", # stop "ON UPDATE CURRENT_TIMESTAMP()"
    ];
}
