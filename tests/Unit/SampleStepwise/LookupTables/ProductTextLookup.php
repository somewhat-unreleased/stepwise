<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables;

use CodeDistortion\Stepwise\Lookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchStepwise;

class ProductTextLookup extends Lookup
{

    protected $stepwiseClass    = ProductSearchStepwise::class;
    protected $tableName        = '%prefix%product_search_%websiteId%_product_text';
    protected $fields           = ['product_id', 'product_text', 'maker_id'];
    protected $primaryKey       = ['product_id'];
    protected $indexes          = [['maker_id']];
    protected $fulltext         = [['product_text']];
    protected $fieldDefinitions = [
        'product_id'                    => "BIGINT(20) UNSIGNED NOT NULL",
        'maker_id'                      => "BIGINT(20) UNSIGNED NOT NULL",
        'product_text'                  => "VARCHAR(255) NULL DEFAULT NULL",
        'product_search_term_relevance' => "DECIMAL(20, 15) NOT NULL",
    ];
}
