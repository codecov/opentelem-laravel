<?php

namespace Codecov\LaravelCodecovOpenTelemetry\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\Trace\Span;
use OpenTelemetry\Trace\SpanStatus;
use OpenTelemetry\Trace\Tracer;

/**
 * Trace an incoming HTTP request.
 */
class Trace
{
    /**
     * @var Tracer OpenTelemetry Tracer
     */
    private $tracer;

    public function __construct(Tracer $tracer = null)
    {
        $this->tracer = $tracer;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$this->tracer) {
            return $next($request);
        }

        $span = $this->tracer->startAndActivateSpan('http_'.strtolower($request->method()));
        $response = $next($request);

        $this->setSpanStatus($span, $response->status());
        $this->addConfiguredTags($span, $request, $response);
        $span->setAttribute('codecov.response.status', $response->status());

        $this->tracer->endActiveSpan();

        return $response;
    }

    private function setSpanStatus(Span $span, int $httpStatusCode)
    {
        switch ($httpStatusCode) {
            case 400:
                $span->setSpanStatus(SpanStatus::FAILED_PRECONDITION, SpanStatus::DESCRIPTION[SpanStatus::FAILED_PRECONDITION]);

                return;

            case 401:
                $span->setSpanStatus(SpanStatus::UNAUTHENTICATED, SpanStatus::DESCRIPTION[SpanStatus::UNAUTHENTICATED]);

                return;

            case 403:
                $span->setSpanStatus(SpanStatus::PERMISSION_DENIED, SpanStatus::DESCRIPTION[SpanStatus::PERMISSION_DENIED]);

                return;

            case 404:
                $span->setSpanStatus(SpanStatus::NOT_FOUND, SpanStatus::DESCRIPTION[SpanStatus::NOT_FOUND]);

                return;
        }

        if ($httpStatusCode >= 500 && $httpStatusCode < 600) {
            $span->setSpanStatus(SpanStatus::ERROR, SpanStatus::DESCRIPTION[SpanStatus::ERROR]);
        }

        if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            $span->setSpanStatus(SpanStatus::OK, SpanStatus::DESCRIPTION[SpanStatus::OK]);
        }
    }

    private function addConfiguredTags(Span $span, Request $request, $response)
    {
        $configurationKey = 'laravel_codecov_opentelemetry.tags.';

        if (config($configurationKey.'environment')) {
            $span->setAttribute('codecov.environment', config('laravel_codecov_opentelemetry.execution_environment'));
        }

        if (config($configurationKey.'release_id')) {
            $span->setAttribute('codecov.release_id', config('laravel_codecov_opentelemetry.release_id'));
        }

        if (config($configurationKey.'path')) {
            $span->setAttribute('codecov.request.path', $request->path());
        }

        if (config($configurationKey.'url')) {
            $span->setAttribute('codecov.request.url', $request->fullUrl());
        }

        if (config($configurationKey.'method')) {
            $span->setAttribute('codecov.request.method', $request->method());
        }

        if (config($configurationKey.'secure')) {
            $span->setAttribute('codecov.request.secure', $request->secure());
        }

        if (config($configurationKey.'ip')) {
            $span->setAttribute('codecov.request.ip', $request->ip());
        }

        if (config($configurationKey.'ua')) {
            $span->setAttribute('codecov.request.ua', $request->userAgent());
        }

        if (config($configurationKey.'user') && $request->user()) {
            $span->setAttribute('codecov.request.user', $request->user()->email);
        }

        if (config($configurationKey.'action') && $request->route()) {
            $span->setAttribute('codecov.request.action', $request->route()->getActionName());
        }

        if (config($configurationKey.'sever') && $request->server()) {
            $span->setAttribute('codecov.request.server', $request->server());
        }
    }
}
