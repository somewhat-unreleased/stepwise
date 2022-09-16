<?php

namespace CodeDistortion\Stepwise\Tests\Unit;

use App;
use CodeDistortion\Stepwise\Internal\QueryTracker;
use CodeDistortion\Stepwise\Tests\TestCase;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductMakerLookup;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test the PreCache Lookup class
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class LookupPopulateUnitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Replacements used when creating Lookups
     *
     * @var array
     */
    protected $stubReplacements = ['prefix' => '_cache1_', 'websiteId' => 1];


    /**
     * Test that the Lookup can populate it's table in the database.
     *
     * @test
     * @return void
     */
//    public function test_populate_lookup_table(): void // @TODO PHP 7.1
    public function test_populate_lookup_table()
    {
        mt_srand(1);
        $data = [];
        foreach ([100] as $makerId) {
            $data[] = ['product_id' => 1, 'maker_id' => $makerId];
        }

        $queryTracker = new QueryTracker();
        $productMakerLookup = (new ProductMakerLookup($this->stubReplacements))
        ->testMode()
        ->createPrimaryTable();

        $productMakerLookup->startPopulateProcess()->willUpdateEverything();
        foreach ($data as $row) {
            $productMakerLookup->addRow($row);
        }
        $productMakerLookup->finishPopulateProcess();

        $this->assertSame([
            "DROP TABLE IF EXISTS `_cache1_product_search_1_product_maker`",

            "CREATE TABLE IF NOT EXISTS `_cache1_product_search_1_product_maker` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."`maker_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`),\n"
                ."KEY `maker_id` (`maker_id`)\n"
            .") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
                ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
                ."PRIMARY KEY `primary` (`product_id`)\n"
            .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
            ."SELECT `product_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`",

            "SELECT `product_id`, `maker_id`\n"
                ."FROM `_cache1_product_search_1_product_maker`\n"
                ."WHERE (`product_id` = 1)",
            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
                ."VALUES (1, 100)",

            "DELETE FROM `_lookup_comparison_1`\n"
                ."WHERE (`product_id` = 1)",

            "DELETE `a` FROM `_cache1_product_search_1_product_maker` AS `a`\n"
            ."JOIN `_lookup_comparison_1` AS `b`\n"
                ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",
        ], $queryTracker->renderedQueries());
//        $this->compareTableContent('_cache1_product_search_1_product_maker', $data);





        mt_srand(1);
        $data = [];
        $usedProductIds = [];
        foreach ([1, 2, 3] as $makerId) {
            $maxProducts = mt_rand(0, 100);
            for ($count = 0; $count < $maxProducts; $count++) {

                $productId = mt_rand(1, 5000);
                if (!isset($usedProductIds[$productId])) {
                    $data[] = ['product_id' => $productId, 'maker_id' => $makerId];

                    $usedProductIds[$productId] = $productId;
                }
            }
        }

        $queryTracker = new QueryTracker();
        $productMakerLookup = (new ProductMakerLookup($this->stubReplacements))
            ->testMode();
//            ->createPrimaryTable();

        $productMakerLookup->startPopulateProcess()->willUpdateEverything();
        foreach ($data as $row) {
            $productMakerLookup->addRow($row);
        }
        $productMakerLookup->finishPopulateProcess();

        $this->assertSame([
            "CREATE TEMPORARY TABLE IF NOT EXISTS `_lookup_comparison_1` (\n"
            ."`product_id` BIGINT(20) UNSIGNED NOT NULL,\n"
            ."PRIMARY KEY `primary` (`product_id`)\n"
            .") ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "INSERT IGNORE INTO `_lookup_comparison_1` (`product_id`)\n"
            ."SELECT `product_id`\n"
            ."FROM `_cache1_product_search_1_product_maker`",

            "SELECT `product_id`, `maker_id`\n"
            ."FROM `_cache1_product_search_1_product_maker`\n"
            ."WHERE (`product_id` IN (1140,125,3369,1264,314,3492,1342,1760,4433,1249,1250,1517,3944,2014,2341,3303,4722,243,2717,1751,4494,347,2205,677,675))",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
            ."VALUES (1140, 1), (125, 1), (3369, 1), (1264, 1), (314, 1), (3492, 1), (1342, 1), (1760, 1), (4433, 1), (1249, 1), (1250, 1), (1517, 1), (3944, 1), (2014, 1), (2341, 1), (3303, 1), (4722, 1), (243, 1), (2717, 1), (1751, 1), (4494, 1), (347, 1), (2205, 1), (677, 2), (675, 2)",

            "DELETE FROM `_lookup_comparison_1`\n"
            ."WHERE (`product_id` IN (1140,125,3369,1264,314,3492,1342,1760,4433,1249,1250,1517,3944,2014,2341,3303,4722,243,2717,1751,4494,347,2205,677,675))",

            "SELECT `product_id`, `maker_id`\n"
            ."FROM `_cache1_product_search_1_product_maker`\n"
            ."WHERE (`product_id` IN (1653,3830,255,1019,1997,488,4538,1274,2304,3167,4393,1311,1726,4930,1786,2224,1624,1446,2999,3250,2722,3665,1365,3866,2089))",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
            ."VALUES (1653, 2), (3830, 2), (255, 2), (1019, 2), (1997, 2), (488, 2), (4538, 3), (1274, 3), (2304, 3), (3167, 3), (4393, 3), (1311, 3), (1726, 3), (4930, 3), (1786, 3), (2224, 3), (1624, 3), (1446, 3), (2999, 3), (3250, 3), (2722, 3), (3665, 3), (1365, 3), (3866, 3), (2089, 3)",

            "DELETE FROM `_lookup_comparison_1`\n"
            ."WHERE (`product_id` IN (1653,3830,255,1019,1997,488,4538,1274,2304,3167,4393,1311,1726,4930,1786,2224,1624,1446,2999,3250,2722,3665,1365,3866,2089))",

            "SELECT `product_id`, `maker_id`\n"
            ."FROM `_cache1_product_search_1_product_maker`\n"
            ."WHERE (`product_id` IN (4321,4022,724,1112,2595,3338,1009,1447,2000,3460,2359))",

            "INSERT IGNORE INTO `_cache1_product_search_1_product_maker` (`product_id`, `maker_id`) \n"
            ."VALUES (4321, 3), (4022, 3), (724, 3), (1112, 3), (2595, 3), (3338, 3), (1009, 3), (1447, 3), (2000, 3), (3460, 3), (2359, 3)",

            "DELETE FROM `_lookup_comparison_1`\n"
            ."WHERE (`product_id` IN (4321,4022,724,1112,2595,3338,1009,1447,2000,3460,2359))",

            "DELETE `a` FROM `_cache1_product_search_1_product_maker` AS `a`\n"
            ."JOIN `_lookup_comparison_1` AS `b`\n"
            ."ON `a`.`product_id` = `b`.`product_id`",

            "DROP TEMPORARY TABLE IF EXISTS `_lookup_comparison_1`",
        ], $queryTracker->renderedQueries());
//        $this->compareTableContent('_cache1_product_search_1_product_maker', $data);





        // the same data but less products for maker 2, more products for maker 3, and a new maker 4
        mt_srand(1);
        $data = [];
        $maker2Count = 0;
        $usedProductIds = [];
        foreach ([1, 2, 3, 4] as $makerId) {
            $maxProducts = mt_rand(0, 100);
            for ($count = 0; $count < $maxProducts; $count++) {

                $productId = mt_rand(1, 5000);
                if (!isset($usedProductIds[$productId])) {
                    $row = ['product_id' => $productId, 'maker_id' => $makerId];
                    if (($makerId != 2) || ($maker2Count++ < 10)) {
                        $data[] = $row;
                    }

                    $usedProductIds[$productId] = $productId;
                }

                if ($makerId == 3) {
                    $productId = mt_rand(1, 5000);
                    if (!isset($usedProductIds[$productId])) {
                        $row = ['product_id' => $productId, 'maker_id' => $makerId];
                        $data[] = $row;

                        $usedProductIds[$productId] = $productId;
                    }
                }
            }
        }

        $productMakerLookup = (new ProductMakerLookup($this->stubReplacements))
            ->testMode();
//            ->createPrimaryTable();

        $productMakerLookup->startPopulateProcess()->willUpdateEverything();
        foreach ($data as $row) {
            $productMakerLookup->addRow($row);
        }
        $productMakerLookup->finishPopulateProcess();
        $this->compareTableContent('_cache1_product_search_1_product_maker', $data);





        // all different products - and added via call to addRows(..) (instead of singularly via addRow)
        for ($salt = 2; $salt <= 5; $salt++) {
            mt_srand($salt);
            $data = [];
            $usedProductIds = [];
            foreach ([2, 3] as $makerId) {
                $maxProducts = mt_rand(0, 20);
                for ($count = 0; $count < $maxProducts; $count++) {

                    $productId = mt_rand(1, 50000);
                    if (!isset($usedProductIds[$productId])) {
                        $data[] = ['product_id' => $productId, 'maker_id' => $makerId];
                        $usedProductIds[$productId] = $productId;
                    }
                }
            }

            (new ProductMakerLookup($this->stubReplacements))
                ->testMode()
                ->startPopulateProcess()
                ->willUpdateEverything()
                ->addRows($data)
                ->finishPopulateProcess();
            $this->compareTableContent('_cache1_product_search_1_product_maker', $data);
        }





        // and empty again
        (new ProductMakerLookup($this->stubReplacements))
            ->testMode()
//            ->createPrimaryTable()
            ->startPopulateProcess()
            ->willUpdateEverything()
            ->finishPopulateProcess();
        $this->compareTableContent('_cache1_product_search_1_product_maker', []);
    }

    /**
     * Compare the contents of the given table with the given array of data
     *
     * @param string $table The db table to load from.
     * @param array  $data  The data to compare it with.
     * @return void
     */
//    protected function compareTableContent(string $table, array $data): void // @TODO PHP 7.1
    protected function compareTableContent(string $table, array $data)
    {
        $sort = function (array $a, array $b) {
            foreach (array_keys($a) as $index) {
                if (isset($b[$index])) {
                    if ($a[$index] < $b[$index]) {
                        return -1;
                    }
                    if ($a[$index] > $b[$index]) {
                        return 1;
                    }
                }
            }
            return 0;
        };

        // load the data from the DB
        $dbData = [];
        foreach (DB::select("SELECT * FROM `".$table."`") as $row) {
            $dbData[] = (array) $row;
        }

        usort($data, $sort);
        usort($dbData, $sort);

        $this->assertSame($data, $dbData);
    }
}
