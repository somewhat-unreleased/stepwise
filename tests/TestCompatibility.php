<?php

namespace CodeDistortion\Stepwise\Tests;

use Closure;
use Mockery;

/**
 * Some helper methods to help with compatibility
 */
trait TestCompatibility
{
    /**
     * Create a mock object for the given class
     *
     * @param string  $class    The class to create the mock for.
     * @param Closure $callback The callback that defines the mock.
     * @return mixed
     */
    protected function compatCreateMock(string $class, Closure $callback)
    {
        // Laravel (somewhere above 5.2)
        if (method_exists($this, 'mock')) {
            return $this->mock($class, $callback);
        }

        return $this->instance($class, Mockery::mock($class, $callback));
    }
}
