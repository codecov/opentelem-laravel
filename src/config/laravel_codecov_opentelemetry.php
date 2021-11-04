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
    | Codecov Host
    |--------------------------------------------------------------------------
    |
    | This value is the URL of your Codecov host. Make sure you include the
    | protocol and (if needed) port number. e.g., https://my-codecov-host:4100
    |
    */

    'codecov_host' => env('CODECOV_OTEL_HOST', 'https://api.codecov.io'),

    /*
    |--------------------------------------------------------------------------
    | Upload Token
    |--------------------------------------------------------------------------
    |
    | The upload token of the repository we're sending information for.
    |
    */

    'profiling_token' => env('CODECOV_OTEL_PROFILING_TOKEN', null),

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
    'profiling_id' => env('CODECOV_OTEL_PROFILING_ID', env('COMMIT_SHA', "default")),

    /*
    |--------------------------------------------------------------------------
    | Tracked Spans Sample Rate
    |--------------------------------------------------------------------------
    |
    | The rate to sample spans with line execution information. For performance reasons, it is not recommended
    | to track every span. The default value ensures one out of ten spans will be tracked on average.
    | Minimum Value: 0 -- no tracked spans, Maximum Value: 100 -- all spans tracked.
    |
    | A value of 0 will effectively disable tracked span creation completely. Useful if you are not interested in
    | any features that require line execution information.
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
    | Minimum Value: 0 -- no tracked spans, Maximum Value: 100 -- all untracked spans.
    |
    */
    'untracked_spans_sample_rate' => env('CODECOV_OTEL_UNTRACKED_SPANS_SAMPLE_RATE', 10),
];
