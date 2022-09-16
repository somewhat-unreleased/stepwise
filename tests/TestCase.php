<?php

namespace CodeDistortion\Stepwise\Tests;

//use PHPUnit\Framework\TestCase as BaseTestCase;
use Jchook\AssertThrows\AssertThrows;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * The test case that unit tests extend from
 */
abstract class TestCase extends BaseTestCase
{
    use AssertThrows;
    use TestCompatibility;
}
