<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables;

use CodeDistortion\Stepwise\Lookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchStepwise;

class ProductPriceLookup extends Lookup
{

    protected $stepwiseClass = ProductSearchStepwise::class;
    protected $tableName     = '%prefix%product_search_%websiteId%_product_prices';
    protected $fields        = ['product_id', 'product_price', 'maker_id'];
    protected $primaryKey    = ['product_id'];
    protected $indexes       = [['product_price', 'maker_id']];
}
