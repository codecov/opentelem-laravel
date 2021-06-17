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

    'enable' => env('CODECOV_CONTRACT_ENABLED', true),

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

    'service_name' => env('CODECOV_CONTRACT_SERVICE_NAME', 'laravel-codecov-opentelem'),

    /*
    |--------------------------------------------------------------------------
    | Codecov Endpoint
    |--------------------------------------------------------------------------
    |
    | This value is the URL of your Codecov endpoint. Make sure you include the
    | protocol and port number.
    |
    */

    'codecov_endpoint' => env('CODECOV_CONTRACT_ENDPOINT', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Upload Token
    |--------------------------------------------------------------------------
    |
    | The upload token of the repository we're sending information for.
    |
    */

    'codecov_token' => env('CODECOV_CONTRACT_TOKEN', null),

    /*
    |--------------------------------------------------------------------------
    | Execution Environment
    |--------------------------------------------------------------------------
    |
    | The environment in which the application is running. Defaults to APP_ENV env var
    | if specified. Otherwise null.
    */
    'execution_environment' => env('CODECOV_CONTRACT_ENVIRONMENT', env('APP_ENV', null)),

    /*
    |--------------------------------------------------------------------------
    | Release Id
    |--------------------------------------------------------------------------
    |
    | A unique id associated with the project being instrumented. It is recommended to change this
    | id on every deployment and have it match across test and production envs. E.g., semver or commit SHA.
    |
    */
    'release_id' => env('CODECOV_CONTRACT_RELEASE_ID', env('COMMIT_SHA', '0.0.0')),

    /*
    |--------------------------------------------------------------------------
    | Tagging
    |--------------------------------------------------------------------------
    |
    | The Trace middleware is able to enrich spans covering a HTTP request with
    | metadata about the request. Using this array you can decide which metadata
    | is included in your spans' tags.
    |
    */

    'tags' => [
        'ip' => true, // Requester's IP address
        'path' => true, // Path requested
        'url' => true, // Full URL requested
        'method' => true, // HTTP method of the request
        'secure' => true, // Whether the request has been secured with SSL/TLS
        'ua' => true, // Requester's user agent
        'user' => true, // Authenticated username
        'action' => true, //Controller action for request if available
        'server' => false, //Request server if available.
        'environment' => true, //The execution environment.
        'release_id' => true, //A unique id for the release being measured, e.g. semver or commit SHA.
    ],
];
