<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// uses(Tests\TestCase::class)->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * returns a simple config to use as a starting off point for various tests.
 */
function testConfig()
{
    $prefix = 'laravel_codecov_opentelemetry.';

    return [
        $prefix.'enable' => true,
        $prefix.'service_name' => 'test-service',
        $prefix.'codecov_host' => 'http://localhost',
        $prefix.'profiling_token' => 'sometoken',
        $prefix.'execution_environment' => 'test',
        $prefix.'profiling_id' => 'abc123',
        $prefix.'tracked_spans_sample_rate' => 100,
        $prefix.'untracked_spans_sample_rate' => 100,
    ];
}
