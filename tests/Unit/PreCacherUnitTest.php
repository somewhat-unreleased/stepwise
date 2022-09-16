<?php

namespace CodeDistortion\Stepwise\Tests\Unit;

use CodeDistortion\Stepwise\Internal\QueryTracker;
use CodeDistortion\Stepwise\Tests\TestCase;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchPreCacher;
use Exception;

/**
 * Test the PreCache Lookup class
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class PreCacherUnitTest extends TestCase
{
    /**
     * Replacements used when creating Lookups
     *
     * @var array
     */
    protected $stubReplacements = ['prefix' => '_cache1_', 'websiteId' => 1];

    /**
     * Provide data for the test_create_tables_from_command test below
     *
     * @return array
     */
    public function createTablesFromCommandDataProvider(): array
    {
        $createProductAllergensTable = [
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_allergens`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_allergens` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`),\n"
                ."KEY `maker_id` (`maker_id`)\n"
                .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        $createProductMakersTable = [
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_maker`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_maker` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `maker_id` (`maker_id`)\n"
                .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        $createProductPricesTable = [
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_prices`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_prices` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`product_price` DECIMAL(18, 3) UNSIGNED NOT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `product_price_maker_id` (`product_price`, `maker_id`)\n"
                .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
        $createProductTextTable = [
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_text`",
            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_text` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`product_text` VARCHAR(255) NULL DEFAULT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `maker_id` (`maker_id`),\n"
                ."FULLTEXT KEY `product_text` (`product_text`)\n"
                .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ];

        return [
            [
                'whitelistClassesParam' => '',
                'blacklistClassesParam' => '',
                'expectedQueries' => [],
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'blah',
                'blacklistClassesParam' => '',
                'expectedQueries' => [],
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'productAllergenLookup,all',
                'blacklistClassesParam' => '',
                'expectedQueries' => [],
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => 'blah',
                'expectedQueries' => [],
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => '',
                'expectedQueries' => array_merge(
                    $createProductAllergensTable,
                    $createProductMakersTable,
                    $createProductPricesTable,
                    $createProductTextTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'productAllergenLookup', // starts with lower case
                'blacklistClassesParam' => '',
                'expectedQueries' => array_merge(
                    $createProductAllergensTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductAllergenLookup', // starts with upper case
                'blacklistClassesParam' => '',
                'expectedQueries' => array_merge(
                    $createProductAllergensTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'productAllergenLookup,ProductMakerLookup',
                'blacklistClassesParam' => '',
                'expectedQueries' => array_merge(
                    $createProductAllergensTable,
                    $createProductMakersTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'productAllergenLookup,ProductMakerLookup',
                'blacklistClassesParam' => 'ProductAllergenLookup',
                'expectedQueries' => array_merge(
                    $createProductMakersTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => 'productAllergenLookup,ProductMakerLookup,ProductPriceLookup',
                'expectedQueries' => array_merge(
                    $createProductTextTable
                ),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' =>
                    'productAllergenLookup,ProductMakerLookup,'
                    .'ProductPriceLookup,ProductTextLookup',
                'expectedQueries' => [],
                'expectedException' => Exception::class,
            ],
        ];
    }

    /**
     * Provide data for the test_pre_cache_tables_from_command test below
     *
     * @return array
     */
    public function preCacheFromCommandDataProvider(): array
    {
        $populateMakerTableAll = [
            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
                ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`",

            "SELECT `product_id`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE (`product_id` IN (101,102,103,104,105))",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
                ."VALUES (101, 1), (102, 2), (103, 3), (104, 4), (105, 5)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` IN (101,102,103,104,105))",

            "DELETE `a` FROM `_cache1_product_search_1_product_maker` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",
        ];

        $populateMakerTableMaker101 = [
            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
                ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE `maker_id` = 101",

            "SELECT `product_id`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE (`product_id` = 201)",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
                ."VALUES (201, 101)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 201)",

            "DELETE `a` FROM `_cache1_product_search_1_product_maker` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",
        ];

        $populateAllMaker101 = [
            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`, `product_allergen_id`)\n"
                ."SELECT `product_id`, `product_allergen_id`\n"
                ."FROM `_cache1_product_search_1_product_allergens`\n"
                ."WHERE `maker_id` = 101",

            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
                ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE `maker_id` = 101",

            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
                ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_prices`\n"
                ."WHERE `maker_id` = 101",

            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
                .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
                ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_text`\n"
                ."WHERE `maker_id` = 101",

            "SELECT `product_id`, `product_allergen_id`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_allergens`\n"
                ."WHERE (`product_id` = 201 AND ((`product_allergen_id` = 2)))",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_allergens` (`product_id`, `product_allergen_id`, `maker_id`) \n"
                ."VALUES (201, 2, 101)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 201 AND ((`product_allergen_id` = 2)))",

            "DELETE `a` FROM `_cache1_product_search_1_product_allergens` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id` AND `a`.`product_allergen_id` = `b`.`product_allergen_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",

            "SELECT `product_id`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE (`product_id` = 201)",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
                ."VALUES (201, 101)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 201)",

            "DELETE `a` FROM `_cache1_product_search_1_product_maker` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",

            "SELECT `product_id`, `product_price`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_prices`\n"
                ."WHERE (`product_id` = 201)",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_prices` (`product_id`, `product_price`, `maker_id`) \n"
                ."VALUES (201, 106.55, 101)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 201)",

            "DELETE `a` FROM `_cache1_product_search_1_product_prices` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",

            "SELECT `product_id`, `product_text`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_text`\n"
                ."WHERE (`product_id` = 201)",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_text` (`product_id`, `product_text`, `maker_id`) \n"
                ."VALUES (201, \"My Product\", 101)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 201)",

            "DELETE `a` FROM `_cache1_product_search_1_product_text` AS `a`\n"
                ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",
        ];



        return [
            [
                'whitelistClassesParam' => 'productMakerLookup', // starts with lower case
                'blacklistClassesParam' => '',
                'updateIds' => [],
                'expectedQueries' => array_merge($populateMakerTableAll),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductMakerLookup', // starts with upper case
                'blacklistClassesParam' => '',
                'updateIds' => [],
                'expectedQueries' => array_merge($populateMakerTableAll),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => '',
                'updateIds' => ['makerIds' => [101]],
                'expectedQueries' => array_merge($populateAllMaker101),
                'expectedException' => null,
            ],


            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['makerIds' => [101]], // camelCase plural
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['maker_ids' => [101]], // snake_case plural
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['MakerIds' => [101]], // StudlyCase plural
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],


            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['makerId' => [101]], // camelCase singular
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['maker_id' => [101]], // snake_case singular
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],
            [
                'whitelistClassesParam' => 'ProductMakerLookup',
                'blacklistClassesParam' => '',
                'updateIds' => ['MakerId' => [101]], // StudlyCase singular
                'expectedQueries' => array_merge($populateMakerTableMaker101),
                'expectedException' => null,
            ],


            [
                'whitelistClassesParam' => 'blah',
                'blacklistClassesParam' => '',
                'updateIds' => [],
                'expectedQueries' => array_merge($populateMakerTableAll),
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => 'blah',
                'updateIds' => [],
                'expectedQueries' => array_merge($populateMakerTableAll),
                'expectedException' => Exception::class,
            ],
            [
                'whitelistClassesParam' => 'all',
                'blacklistClassesParam' => '',
                'updateIds' => ['blahIds' => [123]],
                'expectedQueries' => array_merge($populateMakerTableAll),
                'expectedException' => Exception::class,
            ],
        ];
    }





    /**
     * Test the createTablesFromCommand method
     *
     * @test
     * @dataProvider createTablesFromCommandDataProvider
     * @param string      $whitelistClassesParam Comma separated string of classes to include ('all' for all of them).
     * @param string      $blacklistClassesParam Comma separated string of classes to exclude.
     * @param array       $expectedQueries       The queries that are expected to be run.
     * @param string|null $expectedException     The exception class that is expected.
     * @return void
     */
    public function test_create_tables_from_command(
        string $whitelistClassesParam,
        string $blacklistClassesParam,
        array $expectedQueries,
//        ?string $expectedException // @TODO PHP 7.1
        $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        $queryTracker = new QueryTracker(false);
        $productSearchPreCacher = (new ProductSearchPreCacher())
        ->testMode()
        ->setRunQueries(false)
        ->setQueryTracker($queryTracker);

        if (!is_null($expectedException)) {
            $this->assertThrows($expectedException, function () use (
                $productSearchPreCacher,
                $whitelistClassesParam,
                $blacklistClassesParam
            ) {

                $productSearchPreCacher->createTablesFromCommand(
                    $whitelistClassesParam,
                    $blacklistClassesParam,
                    $this->stubReplacements
                );
            });
        } else {

            $productSearchPreCacher->createTablesFromCommand(
                $whitelistClassesParam,
                $blacklistClassesParam,
                $this->stubReplacements
            );
            // print $queryTracker->copyableQueries();

            $this->assertSame($expectedQueries, $queryTracker->renderedQueries());
        }
    }





    /**
     * Test the preCacheFromCommand method
     *
     * @test
     * @dataProvider preCacheFromCommandDataProvider
     * @param string      $whitelistClassesParam Comma separated string of classes to include ('all' for all of them).
     * @param string      $blacklistClassesParam Comma separated string of classes to exclude.
     * @param array       $updateIds             Assoc array of field-names and values that pre-caching shall run for.
     * @param array       $expectedQueries       The queries that are expected to be run.
     * @param string|null $expectedException     The exception class that is expected.
     * @return void
     */
    public function test_pre_cache_from_command(
        string $whitelistClassesParam,
        string $blacklistClassesParam,
        array $updateIds = [],
        array $expectedQueries,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        $queryTracker = new QueryTracker(false);
        $productSearchPreCacher = (new ProductSearchPreCacher())
            ->testMode()
            ->setRunQueries(false)
            ->setQueryTracker($queryTracker);


        if (!is_null($expectedException)) {
            $this->assertThrows($expectedException, function () use (
                $productSearchPreCacher,
                $whitelistClassesParam,
                $blacklistClassesParam,
                $updateIds
            ) {

                $productSearchPreCacher->populateTablesFromCommand(
                    $whitelistClassesParam,
                    $blacklistClassesParam,
                    $updateIds,
                    $this->stubReplacements
                );
            });
        } else {

            $productSearchPreCacher->populateTablesFromCommand(
                $whitelistClassesParam,
                $blacklistClassesParam,
                $updateIds,
                $this->stubReplacements
            );
//            print $queryTracker->copyableQueries();

            $this->assertSame($expectedQueries, $queryTracker->renderedQueries());
        }
    }
}
