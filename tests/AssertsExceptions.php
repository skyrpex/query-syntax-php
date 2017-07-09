<?php

namespace Tests;

use Exception;

/**
 */
trait AssertsExceptions
{
    /**
     * Assert that an exception is thrown when executing the given callback..
     * @param  callable $callback
     * @param  string   $exceptionClass
     */
    protected function assertExceptionIsThrown(callable $callback, $exceptionClass = Exception::class)
    {
        $exception = null;
        try {
            $callback();
        } catch (Exception $e) {
            $exception = $e;
        }

        $this->assertFalse(is_null($exception), "Expected [{$exceptionClass}] to be thrown.");
        $this->assertInstanceOf($exceptionClass, $exception);
    }
}
