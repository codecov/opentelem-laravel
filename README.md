# laravel-codecov-opentelemetry

_Note: This packaged is part of the [Runtime Insights Early Access Program](https://about.codecov.io/product/feature/runtime-insights/)._

## Purpose

This library aims to make interfacing with Codecov's open telemetry based projects more straightforward for Laravel based applications. It is currently pre-alpha and not recommended for use.

This package is not intended to be a general integration library for open telemetry, zipkin, etc. If you're looking for a more general opentelemetry integration package for Laravel, we currently recommended the package that inspired this one: https://github.com/SeanHood/laravel-opentelemetry

## Requirements and Pre-requisites

1. A repository that is active on [Codecov](https://codecov.io)
2. A profiling token obtainable from Codecov.
3. PHP version >=7.4
4. pcov installed as a PHP extension (see _Installation_ below)

## Installation

### Project Dependencies

Bring this package into your project using Composer as follows:

```
composer require codecov/laravel-codecov-opentelemetry
```

As of version `v0.1.0`, HTTP functionality has switched from Guzzle to HTTPlug to allow for more streamlined integration into applications. Therefore, you must bring your own HTTP client implementation to use this library. [You can read more about this here](https://docs.php-http.org/en/latest/httplug/users.html), but if you're not using an http client in your application already, it is recommended to do the following:

```
composer require php-http/curl-client guzzlehttp/psr7 php-http/message
```

Otherwise, it is recommend to [consult the HTTPlug documentation](https://docs.php-http.org/en/latest/httplug/users.html).

### System Dependencies

In order to sample line execution data, some configuration is required. Specifically, the PCOV extension must be installed and enabled in your `php.ini` file.

Enabling pcov is dependent on the underlying system where you are running PHP. Specific examples are as follows:

#### Ubuntu with PHP 7.4

Installing with Ubuntu is fairly straightforward, one must add the pcov system dependency and ensure pcov is enabled in the `php.ini` file:

```
# Install pcov
$: apt-get update && apt-get -y install php7.4-pcov
```

For other versions of PHP, such as 8.0, you should ensure the needed pcov system package exists, and update the package name with the appropriate version.

#### Alpine with PHP 8.0

The following assumes you're running Alpine 3.14 as a docker image. Dockerfile specific instructions are as follows:

```
FROM alpine:3.14

RUN apk update && apk upgrade && \
    #...your dependencies here
    php8-dev gcc make

# Symling php8 => php
RUN ln -s /usr/bin/php8 /usr/bin/php
RUN ln -s /usr/bin/phpize8 /usr/bin/phpize
RUN ln -s /usr/bin/php-config8 /usr/bin/php-config

# Install pcov
RUN wget https://github.com/FriendsOfPHP/pickle/releases/download/v0.6.0/pickle.phar && mv pickle.phar /usr/local/bin/pickle && chmod +x /usr/local/bin/pickle
RUN pickle install pcov
RUN echo "extension=pcov.so" > /etc/php8/conf.d/php-ext-pcov.ini
RUN echo "pcov.enabled=1" >> /etc/php8/conf.d/php-ext-pcov.ini
```

While this shows one example, the basic steps are as follows:

1. Install the relevant system level dependencies for php and pickle
2. Symlink your PHP version to the canonical defaults (if required)
3. Install pickle
4. Instal pcov via pickle
5. Enable the pcov extension via `.ini` configuration.

If your Alpine configuration already leverages another mechanism to install PHP extensions, it is recommended to use that mechanism instead.

## Configuration

### Environment Variables

This package provides a configuration file that can be published via

```
php artisan vendor:publish
```

and selecting the `Codecov\\LaravelCodecovOpenTelemetry` package from the list that appears.

The following settings are available, and more information can be found in the [configuration file](https://github.com/codecov/laravel-codecov-opentelemetry/blob/main/src/config/laravel_codecov_opentelemetry.php):

| Name                        | Environment Variable Name                | Default                                      | Description                                                                                                                                                                                                          | Required                          |
| --------------------------- | ---------------------------------------- | -------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------- |
| service_name                | CODECOV_OTEL_SERVICE_NAME                | laravel-codecov-opentelemetry                | The name of the service you are profiling. Kebab or snake case is recommended                                                                                                                                        | No                                |
| codecov_host                | CODECOV_OTEL_HOST                        | https://api.codecov.io                       | The host where profiling data will be sent. Override with self-hosted URL if you are a Codecov self-hosted customer.                                                                                                 | No                                |
| profiling_token             | CODECOV_OTEL_PROFILING_TOKEN             | `null`                                       | The token used to identify this repository with Codecov                                                                                                                                                              | Yes                               |
| execution_environment       | CODECOV_OTEL_ENVIRONMENT                 | `APP_ENV` if defined, otherwise null         | Environment where the profiled service is running. `APP_ENV` default should work for most cases.                                                                                                                     | Yes, if `APP_ENV` is not supplied |
| profiling_id                | CODECOV_OTEL_PROFILING_ID                | `COMMIT_SHA` if defined, otherwise "default" | A unique identifier for the specific release. Commit SHA is recommended, but anything can be provided, such as semver.                                                                                               | No                                |
| tracked_spans_sample_rate   | CODECOV_OTEL_TRACKED_SPANS_SAMPLE_RATE   | 10                                           | Percentage of spans that are sampled with execution data. Note that sampling execution data does incur some performance penalty, so 10% is recommended for most services                                             | No                                |
| untracked_spans_sample_rate | CODECOV_OTEL_UNTRACKED_SPANS_SAMPLE_RATE | 10                                           | Percentage of spans that are sampled without execution data. These spans incur a much smaller performance penalty, but do not provide as robust a data set to Codecov, resulting in some functionality being limited | No                                |
| force_sync_requests         | CODECOV_OTEL_FORCE_SYNC_REQUESTS         | false                                        | Forces the HTTP client to make synchronous requests to the Codecov API, even if the underlying client supports asynchronous behavior. If the client does not support asynchronous requests, this setting is ignored. | No                                |

### Codecov.yml Changes

You will need to update your `codecov.yml` as follows:

```
comment:
  layout: 'reach,diff,flags,tree,betaprofiling'
  show_critical_paths: true

```

If you do not have a `codecov.yml` in your project, you can create the file in the root of your project and add the above configuration.

## Usage

This package is currently intended to be used as a Laravel route middleware. After installing the package you should add it to your `$routeMiddleware` in `app/Http/Kernel.php` as follows:

```
    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        //...other middleware
        'codecov.insights' => \Codecov\LaravelCodecovOpenTelemetry\Middleware\Trace::class,
    ];
```

Once added there you can apply the middleware to various routes in your `routes/web.http` file as follows:

```
 Route::middleware(['codecov.insights'])->group(function () {
        //...routes
    });

```

or any other mechanism by which you prefer to add middleware.

## Performance Implications

Using line execution in a production system does incur a performance penalty. However, this penalty may be fairly negligible depending on your use case.

A rough benchmark using Apache Benchmark was generated by visiting the same endpoint of the same Laravel application with line execution sampling enabled and disabled. Apache Benchmark was provided via a [Dockerized implementation](https://hub.docker.com/r/jordi/ab) and was executed with the following command:

```
docker run --rm jordi/ab -v 2 -n 100 -c 10 http://<path-to-application>/login
```

This command was run against the same application with pcov line execution enabled and disabled. In each case the following environment variables were used:

```
# enabled at 10% sampling
CODECOV_OTEL_ENABLED=true
CODECOV_OTEL_TRACKED_SPANS_SAMPLE_RATE=10
CODECOV_OTEL_UNTRACKED_SPANS_SAMPLE_RATE=10
```

```
# disabled
CODECOV_OTEL_ENABLED=false
```

Results are as follows:

| metric                   | disabled | enabled @ 10% |
| ------------------------ | -------- | ------------- |
| Time taken for tests (s) | 8.459    | 13.284        |
| Requests per second      | 11.82    | 7.53          |
| Time per request (ms)    | 84.59    | 132.835       |

While a performance penalty is indicated, it will only be for those 10% of endpoints that are actively sampled. 90% of requests in this case will incur no performance penalty. Furthermore, if a service is heavily trafficked, reducing sampling to 1% may be sufficient.

Also note that performance improvement is a work in progress, and we expect the above metrics to more closely align with subsequent releases.
