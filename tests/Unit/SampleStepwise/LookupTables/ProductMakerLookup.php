<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables;

use CodeDistortion\Stepwise\Lookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchStepwise;

class ProductMakerLookup extends Lookup
{

    protected $stepwiseClass = ProductSearchStepwise::class;
    protected $tableName     = '%prefix%product_search_%websiteId%_product_maker';
    protected $fields        = ['product_id', 'maker_id'];
    protected $primaryKey    = ['product_id'];
    protected $indexes       = [['maker_id']];
}
