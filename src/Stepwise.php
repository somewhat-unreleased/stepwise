<?php

namespace CodeDistortion\Stepwise;

use CodeDistortion\Stepwise\Internal\StepwiseCallerSettables;
use CodeDistortion\Stepwise\Internal\StepwiseInfoForOthers;
use CodeDistortion\Stepwise\Internal\StepwiseSearch;

/**
 * Carries out searches based on look-up tables
 */
abstract class Stepwise
{
    use StepwiseCallerSettables;
    use StepwiseInfoForOthers;
    use StepwiseSearch;

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
    protected $inputClass;

    /**
     * The Lookup classes used by this Stepwise
     *
     * @var array
     */
    protected $lookupClasses = [];

    /**
     * The primary key fields to use when performing a search, unless otherwise specified
     *
     * Each new temp table will have these as their primary key
     * @var array
     */
    protected $defaultPrimaryKey = [];

    /**
     * The possible fields that can be used
     *
     * @var array
     */
    protected $fieldDefinitions = [];

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
    protected $orderByAliases = [];

    /**
     * A list of aliases for filter-methods that can be used when working out which methods to include/exclude from the
     * filter-pathing
     *
     * @var array
     */
    protected $filterAliases = [];

}
