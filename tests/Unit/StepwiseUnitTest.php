<?php

namespace CodeDistortion\Stepwise\Tests\Unit;

use CodeDistortion\Stepwise\Internal\QueryTracker;
use CodeDistortion\Stepwise\Input;
use CodeDistortion\Stepwise\Tests\TestCase;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchStepwise;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\ProductSearchInput;

/**
 * Test the PreCache Stepwise Mechanism classes
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class StepwiseUnitTest extends TestCase
{
    /**
     * Replacements used when creating Lookups
     *
     * @var array
     */
    protected $stubReplacements = ['prefix' => '_cache1_', 'websiteId' => 1];



    /**
     * Provide data for the test_search_steps test below
     *
     * @return array
     */
    public function searchStepsDataProvider(): array
    {
        return [
            [
                'description' => 'no refinements',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'db93f90b7ed846d587a355cb30a2c10c',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'maker-ids - performs an OR (but there\'s only one value)',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['makerIds' => [5]]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` = 5)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'c828e423d797d92fafc3ecd31149402e',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'maker-ids - performs an OR',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['makerIds' => [5, 10]]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` IN (5, 10))",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'c828e423d797d92fafc3ecd31149402e',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'allergen-ids - performs an AND (but there\'s only one value)',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['allergenIds' => [1]]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_allergens` src\n"
                        ."WHERE (`src`.`product_allergen_id` = 1)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`"
                    ],
                'expectedTags' => [
                    'all' => '0334051afb43d66ddfca408879ae708d',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'allergen-ids - performs an AND',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['allergenIds' => [1, 2]]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_allergens` src\n"
                        ."WHERE (`src`.`product_allergen_id` = 1)",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 2)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`",
                    ],
                'expectedTags' => [
                    'all' => '0334051afb43d66ddfca408879ae708d',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_2',
                ],
            ],
            [
                'description' => 'refine allergen-ids but also get maker-ids',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['allergenIds' => [1]]),
                'fieldsToTrack' => ['maker_id'],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`maker_id` BIGINT(20) UNSIGNED NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`, `src`.`maker_id` as `maker_id`\n"
                        ."FROM `_cache1_product_search_1_product_allergens` src\n"
                        ."WHERE (`src`.`product_allergen_id` = 1)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => '0334051afb43d66ddfca408879ae708d',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'allergen-ids and maker-ids',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput([
                    'allergenIds' => [1, 2],
                    'makerIds' => [5, 10],
                ]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` IN (5, 10))",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 1)",
                        "CREATE TEMPORARY TABLE `temp_table_3` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_3`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_2` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 2)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`, `temp_table_3`",
                    ],
                'expectedTags' => [
                    'all' => '9cf7df604d973e7705474faf0dbc14f9',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_3',
                ],
            ],
            [
                'description' => 'price range - only MIN value',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['minPrice' => 50]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_prices` src\n"
                        ."WHERE (`src`.`product_price` >= 50)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'a840b7a8862885265c2e4df22913d3ee',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'price range - only MAX value',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['maxPrice' => 100]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_prices` src\n"
                        ."WHERE (`src`.`product_price` <= 100)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'a840b7a8862885265c2e4df22913d3ee',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'price range',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['minPrice' => 50, 'maxPrice' => 100]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_prices` src\n"
                        ."WHERE (`src`.`product_price` BETWEEN 50 AND 100)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => 'a840b7a8862885265c2e4df22913d3ee',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'price range and maker-ids',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['minPrice' => 50, 'maxPrice' => 100, 'makerIds' => [5]]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` = 5)",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_prices` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_price` BETWEEN 50 AND 100)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`",
                    ],
                'expectedTags' => [
                    'all' => 'fb305721179f51c4eeb731a35aae19c4',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_2',
                ],
            ],
            [
                'description' => 'refine by searchTerm',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['searchTerm' => 'some text']),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE MATCH(`src`.`product_text`) AGAINST(\"some text\" IN BOOLEAN MODE)",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE `src`.`product_text` LIKE \"%some text%\"",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => '5d212ab55b5de8269504d28fb45e9249',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'refine by searchTerm and get relevance',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['searchTerm' => 'some text']),
                'fieldsToTrack' => ['product_search_term_relevance'],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_search_term_relevance` DECIMAL(20, 15) NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`, MATCH(`src`.`product_text`) "
                        ."AGAINST(\"some text\" IN BOOLEAN MODE) as `product_search_term_relevance`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE MATCH(`src`.`product_text`) AGAINST(\"some text\" IN BOOLEAN MODE)",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`, NULL as `product_search_term_relevance`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE `src`.`product_text` LIKE \"%some text%\"",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => '5d212ab55b5de8269504d28fb45e9249',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'refine by searchTerm and get relevance for order-by',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['searchTerm' => 'some text']),
                'fieldsToTrack' => [],
                'orderBy' => ['relevance'],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_search_term_relevance` DECIMAL(20, 15) NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`, MATCH(`src`.`product_text`) "
                        ."AGAINST(\"some text\" IN BOOLEAN MODE) as `product_search_term_relevance`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE MATCH(`src`.`product_text`) AGAINST(\"some text\" IN BOOLEAN MODE)",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`, NULL as `product_search_term_relevance`\n"
                        ."FROM `_cache1_product_search_1_product_text` src\n"
                        ."WHERE `src`.`product_text` LIKE \"%some text%\"",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => '5d212ab55b5de8269504d28fb45e9249',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'refine by searchTerm and maker-id, and get relevance for order-by',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['makerIds' => [5, 10], 'searchTerm' => 'some text']),
                'fieldsToTrack' => [],
                'orderBy' => ['relevance'],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` IN (5, 10))",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_search_term_relevance` DECIMAL(20, 15) NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`, MATCH(`ref`.`product_text`) "
                        ."AGAINST(\"some text\" IN BOOLEAN MODE) as `product_search_term_relevance`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_text` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE MATCH(`ref`.`product_text`) AGAINST(\"some text\" IN BOOLEAN MODE)",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`, NULL as `product_search_term_relevance`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_text` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE `ref`.`product_text` LIKE \"%some text%\"",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`",
                    ],
                'expectedTags' => [
                    'all' => '3e169924938979ac94f1c3db58d3881f',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_2',
                ],
            ],
            [
                'description' => 'user favourites - separate table',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['favourites' => true]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `user_favourites` src\n"
                        ."WHERE (`src`.`user_id` = 99)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`",
                    ],
                'expectedTags' => [
                    'all' => '8eecf1014822324b8bd48b87c57d2bb8',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_1',
                ],
            ],
            [
                'description' => 'random score filter',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput(['randomScore' => true]),
                'fieldsToTrack' => ['product_random_score'],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_random_score` DECIMAL(17, 16) NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`, RAND() as `product_random_score`\n"
                        ."FROM `temp_table_1` src\n"
                        ."LEFT JOIN `_cache1_product_search_1_product_maker` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`",
                    ],
                'expectedTags' => [
                    'all' => '752023414eae798b13838c922f87800d',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_2',
                ],
            ],
            [
                'description' => 'filter-pathing 1',
                'primaryKeyFields' => ['product_id'],
                'searchInput' => new ProductSearchInput([
                    'allergenIds' => [1, 2],
                    'makerIds' => [5, 10],
                ]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [
                    'beforeMakers' => ['+allFilters', '-makerFilter'],
                ],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_allergens` src\n"
                        ."WHERE (`src`.`product_allergen_id` = 1)",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 2)",
                        "CREATE TEMPORARY TABLE `temp_table_3` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_3`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `temp_table_2` src\n"
                        ."JOIN `_cache1_product_search_1_product_maker` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`maker_id` IN (5, 10))",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`, `temp_table_3`",
                    ],
                'expectedTags' => [
                    'all' => 'a493f03066cb576b4b42c11a3af5db28',
                    'beforeMakers' => '0334051afb43d66ddfca408879ae708d',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_3',
                    'beforeMakers' => 'temp_table_2',
                ],
            ],
            [
                'description' => 'primary-key with multiple fields - but not explicitly tracking one of them',
                'primaryKeyFields' => ['product_id', 'product_allergen_id'],
                'searchInput' => new ProductSearchInput([
                    'allergenIds' => [1, 2],
                    'makerIds' => [5, 10],
                ]),
                'fieldsToTrack' => [],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` IN (5, 10))",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`, `ref`.`product_allergen_id` as `product_allergen_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 1)",
                        "CREATE TEMPORARY TABLE `temp_table_3` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_3`\n"
                        ."SELECT `src`.`product_id` as `product_id`, `src`.`product_allergen_id` as `product_allergen_id`\n"
                        ."FROM `temp_table_2` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`src`.`product_allergen_id` = 2)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`, `temp_table_3`"
                    ],
                'expectedTags' => [
                    'all' => '55e34110cbae3fb87df060b40e589ed6',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_3',
                ],
            ],
            [
                'description' => 'primary-key with multiple fields',
                'primaryKeyFields' => ['product_id', 'product_allergen_id'],
                'searchInput' => new ProductSearchInput([
                    'allergenIds' => [1, 2],
                    'makerIds' => [5, 10],
                ]),
                'fieldsToTrack' => ['product_allergen_id', ],
                'orderBy' => [],
                'tags' => [],
                'expectedQueries' =>
                    [   "CREATE TEMPORARY TABLE `temp_table_1` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_1`\n"
                        ."SELECT `src`.`product_id` as `product_id`\n"
                        ."FROM `_cache1_product_search_1_product_maker` src\n"
                        ."WHERE (`src`.`maker_id` IN (5, 10))",
                        "CREATE TEMPORARY TABLE `temp_table_2` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_2`\n"
                        ."SELECT `src`.`product_id` as `product_id`, `ref`.`product_allergen_id` as `product_allergen_id`\n"
                        ."FROM `temp_table_1` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`ref`.`product_allergen_id` = 1)",
                        "CREATE TEMPORARY TABLE `temp_table_3` (\n"
                        ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                        ."`product_allergen_id` SMALLINT(6) UNSIGNED NOT NULL,\n"
                        ."PRIMARY KEY `primary` (`product_id`, `product_allergen_id`)\n"
                        .") ENGINE = MEMORY",
                        "INSERT IGNORE INTO `temp_table_3`\n"
                        ."SELECT `src`.`product_id` as `product_id`, `src`.`product_allergen_id` as `product_allergen_id`\n"
                        ."FROM `temp_table_2` src\n"
                        ."JOIN `_cache1_product_search_1_product_allergens` ref\n"
                        ."ON `src`.`product_id` = `ref`.`product_id`\n"
                        ."WHERE (`src`.`product_allergen_id` = 2)",
                        "DROP TEMPORARY TABLE IF EXISTS `temp_table_1`, `temp_table_2`, `temp_table_3`"
                    ],
                'expectedTags' => [
                    'all' => '55e34110cbae3fb87df060b40e589ed6',
                ],
                'expectedTagTables' => [
                    'all' => 'temp_table_3',
                ],
            ],
        ];
    }





    /**
     * Test the queries that are performed when searching with various filter refinements
     *
     * @test
     * @dataProvider searchStepsDataProvider
     *
     * @param string $description       A description of the input being tested.
     * @param array  $primaryKeyFields  The primary-key fields to use when creating temp tables (if collected yet).
     * @param Input  $input             The input to use as input into the search.
     * @param array  $fieldsToTrack     The fields to track.
     * @param array  $orderBy           The order-by fields/aliases that need fields tracked for.
     * @param array  $tags              The filters to tag before/after.
     * @param array  $expectedQueries   The queries that are expected to be run.
     * @param array  $expectedTags      The tags that are expected to exist after being run.
     * @param array  $expectedTagTables The tables that each tag points to.
     *
     * @return void
     */
    public function test_search_steps(
        string $description,
        array $primaryKeyFields,
        Input $input,
        array $fieldsToTrack,
        array $orderBy,
        array $tags,
        array $expectedQueries,
        array $expectedTags,
        array $expectedTagTables
//    ): void { // @TODO PHP 7.1
    ) {
        $queryTracker = new QueryTracker(false);

        $productSearchStepwise = (new ProductSearchStepwise())
            ->testMode()
            ->setRunQueries(false)
            ->setQueryTracker($queryTracker)
            ->setValue('userId', 99);
        $productSearchStepwise
            ->stubReplacements($this->stubReplacements)
            ->primaryKey($primaryKeyFields)
            ->input($input)
            ->trackFields($fieldsToTrack)
            ->orderBy($orderBy)
            ->tags($tags);
        $productSearchStepwise->search();
        $productSearchStepwise->dropTempTables();

        $tagTempTables = [];
        foreach (array_keys($productSearchStepwise->getFilterRefTags()) as $tagName) {
            $tagTempTables[$tagName] = $productSearchStepwise->getFilterRefTagTempTableName($tagName);
        }


//        dump($description);
//        print PHP_EOL.PHP_EOL.($queryTracker->copyableQueries()).PHP_EOL.PHP_EOL;
//        dump($productSearchStepwise->getTags());
//        dump($tagTempTables);



        // check that the queries ran as expected
        $this->assertSame(
            $expectedQueries,
            $queryTracker->renderedQueries()
        );

        // check that the tags were created as expected
        $this->assertSame(
            $expectedTags,
            $productSearchStepwise->getFilterRefTags()
        );

        // check that the correct temp tables were tagged
        $this->assertSame($expectedTagTables, $tagTempTables);
    }
}
