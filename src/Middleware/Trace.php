<?php

namespace Codecov\LaravelCodecovOpenTelemetry\Middleware;

use Closure;
use Illuminate\Http\Request;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\Span;
use OpenTelemetry\SDK\Trace\SpanStatus;
use OpenTelemetry\SDK\Trace\Tracer;
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

        $span = $this->tracer
            ->spanBuilder('http_'.strtolower($request->method()))
            ->startSpan();

        $coverage = null;

        if (extension_loaded('pcov') && $shouldSampleTracked) {
            $filter = new Filter();
            $filter->includeDirectory(app_path());

            $coverage = new CodeCoverage(
                new PcovDriver($filter),
                $filter
            );

            //if we cannot get the route name, just use the time as a unique identifier.
            $name = $request->route() ? $request->route()->getName() : ''.time();
            $coverage->start($name ?? ''. time());
        }

        $response = $next($request);

        if ($coverage) {
            $coverage->stop();

            $span->setAttribute('codecov.type', 'bytes');
            $span->setAttribute('codecov.coverage', $coverage);
        }

        //In the event we can't get a status, we just use code 520.
        if(!method_exists($response, 'status')) {
            $status = '520';
        } else {
            $status = $response->status();
        }

        $this->setSpanStatus($span, $status);
        $this->addConfiguredTags($span, $request, $response, $status);

        $uri = $request->route() ? $request->route()->uri() : null;
        if($uri) {
            $span->updateName($uri);
        }

        $span->end();

        return $response;
    }

    private function setSpanStatus(Span $span, int $httpStatusCode)
    {
        if ($httpStatusCode >= 400 && $httpStatusCode < 600) {
            $span->setStatus(StatusCode::STATUS_ERROR, $httpStatusCode);
        }

        if ($httpStatusCode >= 200 && $httpStatusCode < 300) {
            $span->setStatus(StatusCode::OK, $httpStatusCode);
        }
    }

    private function addConfiguredTags(Span $span, Request $request, $response, $status)
    {
        $span->setAttribute('http.status_code', $status ?? 'not passed');
        $span->setAttribute('http.method', $request->method()) ?? 'not passed';
        $span->setAttribute('http.host', $request->root() ?? 'not passed');
        $span->setAttribute('http.target', '/'.$request->path() ?? 'not passed');
        $span->setAttribute('http.scheme', $request->secure() ? 'https' : 'http');
        $span->setAttribute('http.flavor', $_SERVER['SERVER_PROTOCOL'] ?? 'not passed');
        $span->setAttribute('http.server_name', $request->server('SERVER_ADDR'));
        $span->setAttribute('http.user_agent', $request->userAgent() ?? 'not passed');
        $span->setAttribute('net.host.port', $request->server('SERVER_PORT') ?? 'not passed');
        $span->setAttribute('net.peer.ip', $request->ip() ?? 'not passed');
        $span->setAttribute('net.peer.port', $_SERVER['REMOTE_PORT'] ?? 'not passed');
    }
}
