<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise;

use CodeDistortion\Stepwise\Clauses\FullTextClause;
use CodeDistortion\Stepwise\Clauses\LikeClause;
use CodeDistortion\Stepwise\Clauses\RawClause;
use CodeDistortion\Stepwise\Clauses\RegularClause;
use CodeDistortion\Stepwise\Stepwise;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductAllergenLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductMakerLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductPriceLookup;
use CodeDistortion\Stepwise\Tests\Unit\SampleStepwise\LookupTables\ProductTextLookup;

class ProductSearchStepwise extends Stepwise
{
    /**
     * The Laravel config keys for each Lookup table-name stub
     *
     * @var array
     */
    protected $stubConfigKeys = [];

    /**
     * The Input class used by this Stepwise
     *
     * @var string
     */
    protected $inputClass = ProductSearchInput::class;

    /**
     * The Lookup classes used by this Stepwise
     *
     * @var array
     */
    protected $lookupClasses = [
        ProductAllergenLookup::class,
        ProductMakerLookup::class,
        ProductPriceLookup::class,
        ProductTextLookup::class,
    ];

    /**
     * The primary key fields to use when performing a search, unless otherwise specified
     *
     * Each new temp table will have these as their primary key
     * @var array
     */
    protected $defaultPrimaryKey = ['product_id'];

    /**
     * The possible fields that can be used
     *
     * @var array
     */
    protected $fieldDefinitions = [
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

    /**
     * The order-by aliases that can be used
     *
     * In the format of:
     * $orderByAliases = [
     *     'price' => [
     *         [
     *             'fields' => 'product_price', (string or array)
     *             'clause' => "`%table%`.`product_price`",
     *             'dir'    => 'ASC',
     *         ],
     *         [
     *             'fields' => 'product_id', (string or array)
     *             'clause' => "`%table%`.`product_id`",
     *             'dir'    => 'DESC',
     *         ],
     *     ],
     *     'name' => [ ...
     * ];
     *
     * @var array
     */
    protected $orderByAliases = [
        'relevance' => [
            [
                'fields' => 'product_search_term_relevance',
                'clause' => "`%table%`.`product_search_term_relevance`",
                'dir'    => 'DESC',
            ],
            [
                'fields' => 'product_id',
                'clause' => "`%table%`.`product_id`",
                'dir'    => 'DESC',
            ],
        ],
        'fallback' => [
            [
                'fields' => 'product_id',
                'clause' => "`%table%`.`product_id`",
                'dir'    => 'ASC',
            ],
        ],
    ];

    /**
     * A list of aliases for filter-methods that can be used when working out which methods to include/exclude from the
     * filter-pathing
     *
     * @var array
     */
    protected $filterAliases = [];





    protected function makerFilter(array $makerIds, bool $allowAlter, bool $actionCheck)
    {
        return $this->regularFilter(
            ProductMakerLookup::class, // $refTable
            'product_id', // $linkingFields
            null, // $extraTrackFields
            [['maker_id' => $makerIds]], // $clauseSets
            $allowAlter, // $allowAlter
            $actionCheck // $actionCheck
        );
    }

    protected function allergenFilter(array $allergenIds, bool $allowAlter, bool $actionCheck)
    {
        $clauseSets = RegularClause::buildClauseSets('product_allergen_id', $allergenIds);

        return $this->regularFilter(
            ProductAllergenLookup::class, // $refTable
            'product_id', // $linkingFields
            null, // $extraTrackFields
            $clauseSets, // $clauseSets
            $allowAlter, // $allowAlter
            $actionCheck // $actionCheck
        );
    }

    protected function priceFilter(
//        ?float $minPrice, // @TODO PHP 7.1
        float $minPrice = null,
//        ?float $maxPrice, // @TODO PHP 7.1
        float $maxPrice = null,
        bool $allowAlter,
        bool $actionCheck
    ) {
        $clause = RegularClause::buildRange('product_price', $minPrice, $maxPrice, 0, null); // not less than 0

        return $this->regularFilter(
            ProductPriceLookup::class, // $refTable
            'product_id', // $linkingFields
            null, // $extraTrackFields
            [[$clause]], // $clauseSets
            $allowAlter, // $allowAlter
            $actionCheck // $actionCheck
        );
    }

    protected function textFilter(
//        ?string $searchTerm, // @TODO PHP 7.1
        string $searchTerm = null,
        bool $allowAlter,
        bool $actionCheck
    ) {
        // set up the situation
        $fullTextClause = (($searchTerm) && ($allowAlter)
            ? new FullTextClause('product_text', $searchTerm, true)
            : null);

        $alter = (bool) $fullTextClause;
        $canTrackFields = [
            'product_id',
            'product_text',
            'maker_id',
            'product_search_term_relevance' => $fullTextClause
        ];
        $linkingFields = ['product_id'];

        // report back if that's what's needed
        if ($actionCheck) {
            return [
                'alter' => $alter,
                'track' => $canTrackFields,
                'link' => $linkingFields,
            ];
        }

        // perform the FULL-TEXT refinement
        $lookupTable = $this->newLookup(ProductTextLookup::class);
        $destTempTable = $this->newTempTable($canTrackFields, $linkingFields);
        $this->runFilterQuery(
            $this->curTempTable(), // $srcTable
            $lookupTable, // $refTable
            $destTempTable, // $destTable
            $linkingFields, // $linkingFields
            $canTrackFields,// $extraTrackFields
            [$fullTextClause], // $clauseSet
            false // $leftJoin
        );

        if (($searchTerm) && ($allowAlter)) {

            // perform the LIKE refinement
            $likeClause = new LikeClause('product_text', '%'.LikeClause::esc($searchTerm).'%');
            $canTrackFields = [
                'product_id',
                'product_text',
                'maker_id',
                'product_search_term_relevance' => new RawClause(null)
            ];
            // $canTrackFields = ['product_text', 'maker_id'];
            $this->runFilterQuery(
                $this->curTempTable(), // $srcTable
                $lookupTable, // $refTable
                $destTempTable, // $destTable
                $linkingFields, // $linkingFields
                $canTrackFields, // $extraTrackFields
                [$likeClause], // $clauseSet
                false // $leftJoin
            );
        }

        $this->useTempTable($destTempTable);
        return;
    }

    protected function favouritesFilter(
//        ?bool $favourites, // @TODO PHP 7.1
        bool $favourites = null,
        bool $allowAlter,
        bool $actionCheck
    ) {
        $userId = $this->getValue('userId');
        if (($favourites) && ($userId)) {
            return $this->regularFilter(
                'user_favourites', // $refTable
                'product_id', // $linkingFields
                null, // $extraTrackFields
                [['user_id' => $userId]], // $clauseSets
                $allowAlter, // $allowAlter
                $actionCheck // $actionCheck
            );
            return;
        }
        return false;
    }

    protected function randomScoreFilter(bool $allowAlter, bool $actionCheck)
    {
        return $this->regularFilter(
            ProductMakerLookup::class, // $refTable
            'product_id', // $linkingFields
            ['product_random_score' => new RawClause('RAND()')], // $extraTrackFields
            null, // $clauseSets
            $allowAlter, // $allowAlter
            $actionCheck // $actionCheck
        );
    }

    protected function fallbackFilter(bool $allowAlter, bool $actionCheck)
    {
        return $this->regularFilter(
            ProductMakerLookup::class,
            'product_id', // $linkingFields
            null, // $extraTrackFields
            null, // $clauseSets
            $allowAlter, // $allowAlter
            $actionCheck, // $actionCheck
            true // $forceAlter
        );
    }
}
