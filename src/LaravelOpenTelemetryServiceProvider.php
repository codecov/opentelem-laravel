<?php

namespace Codecov\LaravelCodecovOpenTelemetry;

use Codecov\LaravelCodecovOpenTelemetry\Codecov\Exporter as CodecovExporter;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\Sdk\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\Sdk\Trace\TracerProvider;
use OpenTelemetry\Trace\Tracer;

/**
 * LaravelOpenTelemetryServiceProvider injects a configured OpenTelemetry Tracer into
 * the Laravel service container, so that instrumentation is traceable.
 */
class LaravelOpenTelemetryServiceProvider extends ServiceProvider
{
    /**
     * Publishes configuration file.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/laravel_codecov_opentelemetry.php' => config_path('laravel_codecov_opentelemetry.php'),
        ]);
    }

    /**
     * Make config publish optional by merging the config from the package.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/laravel_codecov_opentelemetry.php',
            'laravel_codecov_opentelemetry'
        );

        $inst = $this->initOpenTelemetry();

        if ($inst) {
            $this->app->singleton(Tracer::class, function () use ($inst) {
                return $inst;
            });
        }
    }

    /**
     * Initialize an OpenTelemetry Tracer with the exporter
     * specified in the application configuration.
     *
     * @return null|Tracer a configured Tracer, or null if tracing hasn't been enabled
     */
    private function initOpenTelemetry(): ?Tracer
    {
        if (!config('laravel_codecov_opentelemetry.enable') || !config('laravel_codecov_opentelemetry.profiling_token')) {
            return null;
        }

        $exporter = new CodecovExporter(
            config('laravel_codecov_opentelemetry.service_name'),
            config('laravel_codecov_opentelemetry.codecov_host'),
            config('laravel_codecov_opentelemetry.profiling_token')
        );

        $provider = new TracerProvider();

        return $provider
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->getTracer('io.opentelemetry.contrib.php')
        ;
    }
}
