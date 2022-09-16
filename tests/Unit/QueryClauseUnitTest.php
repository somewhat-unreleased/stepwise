<?php

namespace CodeDistortion\Stepwise\Tests\Unit;

use Carbon\Carbon;
use CodeDistortion\Stepwise\Clauses\FullTextClause;
use CodeDistortion\Stepwise\Clauses\LikeClause;
use CodeDistortion\Stepwise\Clauses\RawClause;
use CodeDistortion\Stepwise\Clauses\RegularClause;
use CodeDistortion\Stepwise\Tests\TestCase;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductMakerLookup;
use Exception;

/**
 * Test the PreCache Query Clause classes
 *
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 */
class QueryClauseUnitTest extends TestCase
{
    /**
     * Provide data for the test_regular_query_clauses test below
     *
     * @return array
     */
    public function simpleQueryClauseDataProvider(): array
    {
        return [
            // equal to an array of values
            [
                'fieldName' => 'product_id',
                'operator' => '=',
                'values' => [5],
                'expectedClause' => '(`src`.`product_id` = ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '=',
                'values' => [5, 10],
                'expectedClause' => '(`src`.`product_id` IN (?, ?))',
                'expectedValues' => [5, 10],
                'expectedException' => null,
            ],

            // equal to an empty array (what should it do in this situation?)
            [
                'fieldName' => 'product_id',
                'operator' => '=',
                'values' => [],
                'expectedClause' => '()',
                'expectedValues' => [],
                'expectedException' => null,
            ],

            // comparison to a scalar
            [
                'fieldName' => 'product_id',
                'operator' => '<',
                'values' => 5,
                'expectedClause' => '(`src`.`product_id` < ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '<=',
                'values' => 5,
                'expectedClause' => '(`src`.`product_id` <= ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '=',
                'values' => 5,
                'expectedClause' => '(`src`.`product_id` = ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '>=',
                'values' => 5,
                'expectedClause' => '(`src`.`product_id` >= ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '>',
                'values' => 5,
                'expectedClause' => '(`src`.`product_id` > ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],

            // comparison to an array of values -> exception
            [
                'fieldName' => 'product_id',
                'operator' => '<',
                'values' => [5],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '<=',
                'values' => [5],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '>=',
                'values' => [5],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => '>',
                'values' => [5],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],

            // between various invalid value/s -> exception
            [
                'fieldName' => 'product_id',
                'operator' => 'BETWEEN',
                'values' => 5,
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => 'BETWEEN',
                'values' => [5],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => 'BETWEEN',
                'values' => [5, 10, 11],
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],

            // between different valid values
            [
                'fieldName' => 'product_id',
                'operator' => 'BETWEEN',
                'values' => [5, 10],
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [5, 10],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'operator' => 'BETWEEN',
                'values' => [5, 5],
                'expectedClause' => '(`src`.`product_id` = ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
        ];
    }

    /**
     * Provide data for the test_regular_query_range_clauses test below
     *
     * @return array
     */
    public function simpleQueryClauseBuildRangeDataProvider(): array
    {
        $carbon1 = Carbon::parse('2019-08-31 00:00:00', 'UTC');
        $carbon1b = Carbon::parse('2019-08-31 00:05:00', 'UTC');
        $carbon2 = Carbon::parse('2019-08-31 01:00:00', 'UTC');
        $carbon2b = Carbon::parse('2019-08-31 00:55:00', 'UTC');

        // test with number bounds
        return [
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => null,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` >= ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => 10,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [5, 10],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => null,
                'max' => 10,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` <= ?)',
                'expectedValues' => [10],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => 5,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` = ?)',
                'expectedValues' => [5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => 10,
                'lowestMin' => 6,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [6, 10],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => 10,
                'lowestMin' => null,
                'highestMax' => 9,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [5, 9],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => 5,
                'max' => 10,
                'lowestMin' => 6,
                'highestMax' => 9,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [6, 9],
                'expectedException' => null,
            ],
            [   // reversed min/max
                'fieldName' => 'product_id',
                'min' => 10,
                'max' => 5,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [5, 10],
                'expectedException' => null,
            ],

            // test with Carbon bounds
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => null,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` >= ?)',
                'expectedValues' => [$carbon1],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => $carbon2,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [$carbon1, $carbon2],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => null,
                'max' => $carbon2,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` <= ?)',
                'expectedValues' => [$carbon2],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => $carbon1,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` = ?)',
                'expectedValues' => [$carbon1],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => $carbon2,
                'lowestMin' => $carbon1b,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [$carbon1b, $carbon2],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => $carbon2,
                'lowestMin' => null,
                'highestMax' => $carbon2b,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [$carbon1, $carbon2b],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_id',
                'min' => $carbon1,
                'max' => $carbon2,
                'lowestMin' => $carbon1b,
                'highestMax' => $carbon2b,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [$carbon1b, $carbon2b],
                'expectedException' => null,
            ],
            [   // reversed min/max
                'fieldName' => 'product_id',
                'min' => $carbon2,
                'max' => $carbon1,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '(`src`.`product_id` BETWEEN ? AND ?)',
                'expectedValues' => [$carbon1, $carbon2],
                'expectedException' => null,
            ],

            // some invalid input
            [
                'fieldName' => 'product_id',
                'min' => 'a',
                'max' => null,
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'min' => null,
                'max' => 'b',
                'lowestMin' => null,
                'highestMax' => null,
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'min' => null,
                'max' => null,
                'lowestMin' => 'c',
                'highestMax' => null,
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
            [
                'fieldName' => 'product_id',
                'min' => null,
                'max' => null,
                'lowestMin' => null,
                'highestMax' => 'd',
                'expectedClause' => '',
                'expectedValues' => [],
                'expectedException' => Exception::class,
            ],
        ];
    }

    /**
     * Provide data for the test_regular_query_build_clause_sets_method test below
     *
     * @return array
     */
    public function simpleQueryClauseClauseSetsDataProvider(): array
    {
        return [
            [
                'fieldName' => 'product_cat_id',
                'values' => [],
                'expectedClauses' => [],
                'expectedValues' => [],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_cat_id',
                'values' => [1],
                'expectedClauses' => [
                    ['(`src`.`product_cat_id` = ?)'],
                ],
                'expectedValues' => [1],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_cat_id',
                'values' => [1, 5],
                'expectedClauses' => [
                    ['(`src`.`product_cat_id` = ?)'],
                    ['(`src`.`product_cat_id` = ?)'],
                ],
                'expectedValues' => [1, 5],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_cat_id',
                'values' => [1, 5, 10],
                'expectedClauses' => [
                    ['(`src`.`product_cat_id` = ?)'],
                    ['(`src`.`product_cat_id` = ?)'],
                    ['(`src`.`product_cat_id` = ?)'],
                ],
                'expectedValues' => [1, 5, 10],
                'expectedException' => null,
            ],
        ];
    }

    /**
     * Provide data for the test_fulltext_query_clauses test below
     *
     * @return array
     */
    public function fullTextQueryClauseDataProvider(): array
    {
        return [
            [
                'fieldNames' => 'product_name',
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => true,
                'expectedClause' => 'MATCH(`src`.`product_name`) AGAINST(? IN BOOLEAN MODE)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],
            [
                'fieldNames' => ['product_name'],
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => true,
                'expectedClause' => 'MATCH(`src`.`product_name`) AGAINST(? IN BOOLEAN MODE)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],
            [
                'fieldNames' => ['product_name', 'product_desc'],
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => true,
                'expectedClause' => 'MATCH(`src`.`product_name`, `src`.`product_desc`) AGAINST(? IN BOOLEAN MODE)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],

            [
                'fieldNames' => 'product_name',
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => false,
                'expectedClause' => 'MATCH(`src`.`product_name`) AGAINST(?)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],
            [
                'fieldNames' => ['product_name'],
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => false,
                'expectedClause' => 'MATCH(`src`.`product_name`) AGAINST(?)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],
            [
                'fieldNames' => ['product_name', 'product_desc'],
                'against' => '+("abc") +("def" "ghi")',
                'inBooleanMode' => false,
                'expectedClause' => 'MATCH(`src`.`product_name`, `src`.`product_desc`) AGAINST(?)',
                'expectedValues' => ['+("abc") +("def" "ghi")'],
                'expectedException' => null,
            ],
        ];
    }

    /**
     * Provide data for the test_like_query_clauses test below
     *
     * @return array
     */
    public function likeQueryClauseDataProvider(): array
    {
        return [
            [
                'fieldName' => 'product_name',
                'like' => null,
                'expectedClause' => '`src`.`product_name` LIKE ?',
                'expectedValues' => [null],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_name',
                'like' => '%'.LikeClause::esc('hello').'%',
                'expectedClause' => '`src`.`product_name` LIKE ?',
                'expectedValues' => ['%hello%'],
                'expectedException' => null,
            ],
            [
                'fieldName' => 'product_name',
                'like' => '%'.LikeClause::esc('h%e_l\\lo').'%',
                'expectedClause' => '`src`.`product_name` LIKE ?',
                'expectedValues' => ['%h\%e\_l\\\\lo%'],
                'expectedException' => null,
            ],
        ];
    }

    /**
     * Provide data for the test_raw_query_clauses test below
     *
     * @return array
     */
    public function rawQueryClauseDataProvider(): array
    {
        return [
            [
                'raw' => null,
                'expectedClause' => 'NULL',
                'expectedValues' => [],
                'expectedException' => null,
            ],
            [
                'raw' => 'NULL',
                'expectedClause' => 'NULL',
                'expectedValues' => [],
                'expectedException' => null,
            ],
            [
                'raw' => 'RAND()',
                'expectedClause' => 'RAND()',
                'expectedValues' => [],
                'expectedException' => null,
            ],
        ];
    }







    /**
     * Test the RegularClause class
     *
     * @test
     * @dataProvider simpleQueryClauseDataProvider
     * @param string      $fieldName         The field name this clause is about.
     * @param string      $operator          The operator to be applied.
     * @param mixed       $values            The values to use in comparison.
     * @param string      $expectedClause    The expected clause when rendered.
     * @param array       $expectedValues    The query values expected to be produced by the render.
     * @param string|null $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_regular_query_clauses(
        string $fieldName,
        string $operator,
        $values,
        string $expectedClause,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($fieldName, $operator, $values) {
                    $clause = new RegularClause($fieldName, $operator, $values);
                }
            );
        // or assert that the desired clause is produced
        } else {

            $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                $mock->shouldReceive('hasField')->andReturn(true);
            });

            $clause = new RegularClause($fieldName, $operator, $values);

            $queryValues = [];
            $renderedClause = $clause->render(['src' => $lookup], $queryValues);

            $this->assertSame($expectedClause, $renderedClause);
            $this->assertSame($expectedValues, $queryValues);
        }
    }

    /**
     * Test the RegularClause class buildRange method
     *
     * @test
     * @dataProvider simpleQueryClauseBuildRangeDataProvider
     * @param string                    $fieldName         The field name the new clause is about.
     * @param integer|float|Carbon|null $min               The minimum bound to compare to.
     * @param integer|float|Carbon|null $max               The maximum bound to compare to.
     * @param integer|float|Carbon|null $lowestMin         The lowest the minimum value can be.
     * @param integer|float|Carbon|null $highestMax        The highest the minimum value can be.
     * @param string                    $expectedClause    The expected clause when rendered.
     * @param array                     $expectedValues    The query values expected to be produced by the render.
     * @param string|null               $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_regular_query_range_clauses(
        string $fieldName,
        $min,
        $max,
        $lowestMin,
        $highestMax,
        string $expectedClause,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($fieldName, $min, $max, $lowestMin, $highestMax) {
                    $clause = RegularClause::buildRange($fieldName, $min, $max, $lowestMin, $highestMax);
                }
            );
        // or assert that the desired clause is produced
        } else {

            $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                $mock->shouldReceive('hasField')->andReturn(true);
            });

            $clause = RegularClause::buildRange($fieldName, $min, $max, $lowestMin, $highestMax);
            $queryValues = [];

            $this->assertSame($expectedClause, $clause->render(['src' => $lookup], $queryValues));
            $this->assertSame($expectedValues, $queryValues);
        }
    }

    /**
     * Test the RegularClause class buildClauseSets method
     *
     * @test
     * @dataProvider simpleQueryClauseClauseSetsDataProvider
     * @param string      $fieldName         The field name this clause is about.
     * @param mixed       $values            The values to use in comparison.
     * @param array       $expectedClauses   The expected clauses when rendered.
     * @param array       $expectedValues    The query values expected to be produced by the render.
     * @param string|null $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_regular_query_build_clause_sets_method(
        string $fieldName,
        $values,
        array $expectedClauses,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($fieldName, $values) {
                    $clauseSets = RegularClause::buildClauseSets($fieldName, $values);
                }
            );
        // or assert that the desired clause is produced
        } else {
            $clauseSets = RegularClause::buildClauseSets($fieldName, $values);

            $renderedClauses = $queryValues = [];
            foreach ($clauseSets as $clauseSet) {
                $tempRenderedClauses = [];
                foreach ($clauseSet as $clause) {

                    $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                        $mock->shouldReceive('hasField')->andReturn(true);
                    });

                    $tempRenderedClauses[] = $clause->render(['src' => $lookup], $queryValues);
                }
                if (count($tempRenderedClauses)) {
                    $renderedClauses[] = $tempRenderedClauses;
                }
            }

            $this->assertSame($expectedClauses, $renderedClauses);
            $this->assertSame($expectedValues, $queryValues);
        }
    }

    /**
     * Test the FulltextClause class
     *
     * @test
     * @dataProvider fullTextQueryClauseDataProvider
     * @param string|array $fieldNames        The field-name/s to use in the clause.
     * @param string       $against           The AGAINST part of the clause.
     * @param boolean      $inBooleanMode     Whether BOOLEAN MODE is to be used.
     * @param string       $expectedClause    The expected clause when rendered.
     * @param array        $expectedValues    The query values expected to be produced by the render.
     * @param string|null  $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_fulltext_query_clauses(
        $fieldNames,
        string $against,
        bool $inBooleanMode,
        string $expectedClause,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($fieldNames, $against, $inBooleanMode) {
                    $clause = new FulltextClause($fieldNames, $against, $inBooleanMode);
                }
            );
        // or assert that the desired clause is produced
        } else {

            $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                $mock->shouldReceive('hasField')->andReturn(true);
            });

            $clause = new FulltextClause($fieldNames, $against, $inBooleanMode);
            $queryValues = [];

            $this->assertSame(
                $expectedClause,
                $clause->render(['src' => $lookup], $queryValues)
            );
            $this->assertSame($expectedValues, $queryValues);
        }
    }

    /**
     * Test the LikeClause class
     *
     * @test
     * @dataProvider likeQueryClauseDataProvider
     * @param string      $fieldName         The field-name to use in the clause.
     * @param string|null $like              The LIKE part of the clause.
     * @param string      $expectedClause    The expected clause when rendered.
     * @param array       $expectedValues    The query values expected to be produced by the render.
     * @param string|null $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_like_query_clauses(
        string $fieldName,
//        ?string $like, // @TODO PHP 7.1
        string $like = null,
        string $expectedClause,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($fieldName, $like) {
                    $clause = new LikeClause($fieldName, $like);
                }
            );
        // or assert that the desired clause is produced
        } else {

            $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                $mock->shouldReceive('hasField')->andReturn(true);
            });

            $clause = new LikeClause($fieldName, $like);
            $queryValues = [];

            $this->assertSame(
                $expectedClause,
                $clause->render(['src' => $lookup], $queryValues)
            );
            $this->assertSame($expectedValues, $queryValues);
        }
    }

    /**
     * Test the RawClause class
     *
     * @test
     * @dataProvider rawQueryClauseDataProvider
     * @param string|null $raw               The LIKE part of the clause.
     * @param string      $expectedClause    The expected clause when rendered.
     * @param array       $expectedValues    The query values expected to be produced by the render.
     * @param string|null $expectedException The exception that should be produced (if any).
     * @return void
     */
    public function test_raw_query_clauses(
//        ?string $raw, // @TODO PHP 7.1
        string $raw = null,
        string $expectedClause,
        array $expectedValues,
//        ?string $expectedException // @TODO PHP 7.1
        string $expectedException = null
//    ): void { // @TODO PHP 7.1
    ) {

        // assert that the desired exception occurs
        if ($expectedException) {
            $this->assertThrows(
                $expectedException,
                function () use ($raw) {
                    $clause = new RawClause($raw);
                }
            );
        // or assert that the desired clause is produced
        } else {

            $lookup = $this->compatCreateMock(ProductMakerLookup::class, function ($mock) {
                $mock->shouldReceive('hasField')->andReturn(true);
            });

            $clause = new RawClause($raw);
            $queryValues = [];

            $this->assertSame(
                $expectedClause,
                $clause->render(['src' => $lookup], $queryValues)
            );
            $this->assertSame($expectedValues, $queryValues);
        }
    }
}
