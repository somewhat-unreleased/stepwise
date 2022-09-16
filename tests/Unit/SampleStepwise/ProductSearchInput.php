<?php

namespace CodeDistortion\Stepwise\Tests\Unit\SampleStepwise;

use CodeDistortion\Stepwise\Input;

class ProductSearchInput extends Input
{
    /**
     * Returns whether the search should proceed or not
     *
     * @return boolean
     */
    public function searchCanProceed(): bool
    {
        return true;
    }
}
