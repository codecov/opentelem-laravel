<?php

namespace Codecov\LaravelCodecovOpenTelemetry\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\Trace\Span;
use OpenTelemetry\Trace\SpanStatus;
use OpenTelemetry\Trace\Tracer;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\PcovDriver;
use SebastianBergmann\CodeCoverage\Filter;

/**
 * Trace an incoming HTTP request.
 */
class Trace
{
    /**
     * @var Tracer OpenTelemetry Tracer
     */
    private $tracer;

    /**
     * @var int
     */
    private $trackedSampleRate;

    /**
     * @var int
     */
    private $untrackedSampleRate;

    public function __construct(Tracer $tracer = null)
    {
        $this->tracer = $tracer;
        $this->trackedSampleRate = config('laravel_codecov_opentelemetry.tracked_spans_sample_rate');
        $this->untrackedSampleRate = config('laravel_codecov_opentelemetry.untracked_spans_sample_rate');
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
        $shouldSampleTracked = $this->trackedSampleRate > rand(0, 100) ? true : false;
        $shouldSampleUntracked = $this->untrackedSampleRate > rand(0, 100) ? true : false;

        if (!$this->tracer || (!$shouldSampleTracked && !$shouldSampleUntracked)) {
            return $next($request);
        }

        $span = $this->tracer->startAndActivateSpan('http_'.strtolower($request->method()));

        $coverage = null;

        if (extension_loaded('pcov') && $shouldSampleTracked) {
            $filter = new Filter();
            $filter->includeDirectory(app_path());

            $coverage = new CodeCoverage(
                new PcovDriver($filter),
                $filter
            );

            $coverage->start($request->route()->getName());
        }

        $response = $next($request);

        if ($coverage) {
            $coverage->stop();

            $span->setAttribute('codecov.type', 'bytes');
            $span->setAttribute('codecov.coverage', $coverage);
        }

        $this->setSpanStatus($span, $response->status());
        $this->addConfiguredTags($span, $request, $response);

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
        $span->setAttribute('http.status_code', $response->status());
        $span->setAttribute('http.method', $request->method());
        $span->setAttribute('http.host', $request->root());
        $span->setAttribute('http.target', '/'.$request->path());
        $span->setAttribute('http.scheme', $request->secure() ? 'https' : 'http');
        $span->setAttribute('http.flavor', $_SERVER['SERVER_PROTOCOL']);
        $span->setAttribute('http.server_name', $request->server('SERVER_ADDR'));
        $span->setAttribute('http.user_agent', $request->userAgent());
        $span->setAttribute('net.host.port', $request->server('SERVER_PORT'));
        $span->setAttribute('net.peer.ip', $request->ip());
        $span->setAttribute('net.peer.port', $_SERVER['REMOTE_PORT']);
    }
}
