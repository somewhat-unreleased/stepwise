<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables;

use CodeDistortion\Stepwise\Lookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchStepwise;

class ProductAllergenLookup extends Lookup
{

    protected $stepwiseClass = ProductSearchStepwise::class;
    protected $tableName     = '%prefix%product_search_%websiteId%_product_allergens';
    protected $fields        = ['product_id', 'product_allergen_id', 'maker_id'];
    protected $primaryKey    = ['product_id', 'product_allergen_id'];
    protected $indexes       = [['maker_id']];
}
