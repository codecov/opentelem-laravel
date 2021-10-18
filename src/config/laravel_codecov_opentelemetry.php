<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Tracing
    |--------------------------------------------------------------------------
    |
    | This value determines whether or not requests are traced using OpenTelemetry
    | instrumentation.
    |
    */

    'enable' => env('CODECOV_OTEL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Service Name
    |--------------------------------------------------------------------------
    |
    | This is the service name that is sent to your tracing infrastructure. If
    | this is a system made up of multiple components (eg: a microservices
    | architecture), then you should use a specific name that will tell you
    | where in the system a request has been sent.
    |
    */

    'service_name' => env('CODECOV_OTEL_SERVICE_NAME', 'laravel-codecov-opentelem'),

    /*
    |--------------------------------------------------------------------------
    | Codecov Endpoint
    |--------------------------------------------------------------------------
    |
    | This value is the URL of your Codecov endpoint. Make sure you include the
    | protocol and port number.
    |
    */

    'codecov_endpoint' => env('CODECOV_OTEL_ENDPOINT', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Upload Token
    |--------------------------------------------------------------------------
    |
    | The upload token of the repository we're sending information for.
    |
    */

    'codecov_token' => env('CODECOV_OTEL_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Execution Environment
    |--------------------------------------------------------------------------
    |
    | The environment in which the application is running. Defaults to APP_ENV env var
    | if specified. Otherwise null.
    */
    'execution_environment' => env('CODECOV_OTEL_ENVIRONMENT', env('APP_ENV', null)),

    /*
    |--------------------------------------------------------------------------
    | Profiling Id
    |--------------------------------------------------------------------------
    |
    | A unique id associated with the project being instrumented. It is recommended to change this
    | id on every deployment and have it match across test and production envs. E.g., semver or commit SHA.
    |
    */
    'profiling_id' => env('CODECOV_OTEL_PROFILING_ID', env('COMMIT_SHA', '0.0.0')),

    /*
    |--------------------------------------------------------------------------
    | Tracked Spans Sample Rate
    |--------------------------------------------------------------------------
    |
    | The rate to sample spans with line execution information. For performance reasons, it is not recommended
    | to track every span. The default value ensures one out of ten spans will be tracked on average.
    |
    */
    'tracked_spans_sample_rate' => env('CODECOV_OTEL_TRACKED_SPANS_SAMPLE_RATE', 10),

    /*
    |--------------------------------------------------------------------------
    | Untracked Spans Sample Rate
    |--------------------------------------------------------------------------
    |
    | The rate to sample spans without line execution information. For performance reasons, it is not recommended
    | to track every span. The default value ensures one out of five spans will be tracked on average.
    |
    | Generally untracked spans will be much more efficient to track.
    |
    */
    'untracked_spans_sample_rate' => env('CODECOV_OTEL_UNTRACKED_SPANS_SAMPLE_RATE', 10),
];
