<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise;

use CodeDistortion\Stepwise\PreCacher;

class ProductSearchPreCacher extends PreCacher
{
    /**
     * The Stepwise class this PreCacher belongs to
     *
     * @var string
     */
    protected $stepwiseClass = ProductSearchStepwise::class;

    /**
     * Methods to skip when looking for xxxPreCache methods
     *
     * @var array
     */
    protected $methodSkipList = [];





    protected function loadAllMakerIds(): array
    {
        return [1, 2, 3, 4, 5];
    }





//    protected function beforePreCache(bool $productMakerLookup): void // @TODO PHP 7.1
    protected function beforePreCache(bool $productMakerLookup)
    {
    }
//    protected function afterPreCache(bool $productAllergenLookup, bool $productMakerLookup): void // @TODO PHP 7.1
    protected function afterPreCache(bool $productAllergenLookup, bool $productMakerLookup)
    {
    }





//    protected function beforeProductPreCache(bool $productMakerLookup): void // @TODO PHP 7.1
    protected function beforeProductPreCache(bool $productMakerLookup)
    {
    }
//    protected function afterProductPreCache(bool $productAllergenLookup, bool $productMakerLookup): void // @TODO PHP 7.1
    protected function afterProductPreCache(bool $productAllergenLookup, bool $productMakerLookup)
    {
    }
    protected function productPreCache(
        array $makerIds,
        $productAllergenLookup = null,
        $productMakerLookup = null,
        $productPriceLookup = null,
        $productTextLookup = null
//    ): void { // @TODO PHP 7.1
    ) {
        foreach ($makerIds as $makerId) {

            if ($productAllergenLookup) {
                $productAllergenLookup->addRow(
                    ['product_id' => $makerId + 100, 'product_allergen_id' => 2, 'maker_id' => $makerId]
                );
            }
            if ($productMakerLookup) {
                $productMakerLookup->addRow(
                    ['product_id' => $makerId + 100, 'maker_id' => $makerId]
                );
            }
            if ($productPriceLookup) {
                $productPriceLookup->addRow(
                    ['product_id' => $makerId + 100, 'product_price' => $makerId + 5.55, 'maker_id' => $makerId]
                );
            }
            if ($productTextLookup) {
                $productTextLookup->addRow(
                    ['product_id' => $makerId + 100, 'product_text' => 'My Product', 'maker_id' => $makerId]
                );
            }

        }
    }
}
