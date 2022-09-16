<?php

namespace CodeDistortion\Stepwise\Tests\Unit;

use CodeDistortion\Stepwise\Internal\QueryTracker;
use CodeDistortion\Stepwise\Tests\TestCase;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductAllergenLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductMakerLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductTextLookup;

/**
 * Test the PreCache Lookup class
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class LookupUnitTest extends TestCase
{
    /**
     * Replacements used when creating Lookups
     *
     * @var array
     */
    protected $stubReplacements = ['prefix' => '_cache1_', 'websiteId' => 1];


    /**
     * Check that the various aspects of the ProductMakerLookup are correct
     *
     * @test
     * @return void
     */
//    public function test_product_maker_lookup(): void // @TODO PHP 7.1
    public function test_product_maker_lookup()
    {
        $productMakerLookup = new ProductMakerLookup($this->stubReplacements);

        $this->assertSame('_cache1_product_search_1_product_maker', $productMakerLookup->getTableName());
        $this->assertSame(['product_id', 'maker_id'], $productMakerLookup->getFieldNames());
        $this->assertTrue($productMakerLookup->hasField('product_id'));
        $this->assertFalse($productMakerLookup->hasField('abc'));

        $queryTracker = new QueryTracker(false);

        (new ProductMakerLookup($this->stubReplacements))
            ->setRunQueries(false)
            ->setQueryTracker($queryTracker)
            ->createPrimaryTable();

        $this->assertSame([
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_maker`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_maker` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `maker_id` (`maker_id`)\n"
            .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ], $queryTracker->renderedQueries());
    }

    /**
     * Check that the various aspects of the ProductTextLookup are correct
     *
     * @test
     * @return void
     */
//    public function test_product_text_lookup(): void // @TODO PHP 7.1
    public function test_product_text_lookup()
    {
        $productTextLookup = new ProductTextLookup($this->stubReplacements);

        $this->assertSame('_cache1_product_search_1_product_text', $productTextLookup->getTableName());
        $this->assertSame(['product_id', 'product_text', 'maker_id'], $productTextLookup->getFieldNames());
        $this->assertTrue($productTextLookup->hasField('product_id'));
        $this->assertFalse($productTextLookup->hasField('abc'));

        $queryTracker = new QueryTracker(false);

        (new ProductTextLookup($this->stubReplacements))
            ->setRunQueries(false)
            ->setQueryTracker($queryTracker)
            ->createPrimaryTable();

        $this->assertSame([
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_text`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_text` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`product_text` VARCHAR(255) NULL DEFAULT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `maker_id` (`maker_id`),\n"
                ."FULLTEXT KEY `product_text` (`product_text`)\n"
            .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ], $queryTracker->renderedQueries());
    }
}
