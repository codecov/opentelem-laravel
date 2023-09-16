<?php

use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

use Codecov\LaravelCodecovOpenTelemetry\Codecov\Exporter as CodecovExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;


use Codecov\LaravelCodecovOpenTelemetry\Middleware\Trace as CodecovTrace;


uses(TestCase::class);

beforeEach(function () {
    $config = testConfig();
    config($config);
});

it('properly handles no status response', function(){
    //mock a binary response.
    $response = Mockery::mock(BinaryFileResponse::class);

    $request = Request::create('/example', 'GET');

    $provider = new TracerProvider();

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );

    $tracer = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->build()
            ->getTracer('io.opentelemetry.contrib.php');

    $traceMiddleware = new CodecovTrace($tracer);

    $newResp = $traceMiddleware->handle($request, function() use($response) {
        return $response;
    });

    //make sure we get our mocked response back and handle() hasn't blown up.
    expect($newResp)->toBe($response);

});

it('properly handles a standard response', function(){
    //mock a binary response.
    $response = Mockery::mock(Response::class)
    ->shouldReceive('status')
    ->andReturn('200');

    $request = Request::create('/example', 'GET');

    $provider = new TracerProvider();

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );

    $tracer = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->build()
            ->getTracer('io.opentelemetry.contrib.php');

    $traceMiddleware = new CodecovTrace($tracer);

    $newResp = $traceMiddleware->handle($request, function() use($response) {
        return $response;
    });

    //make sure we get our mocked response back and handle() hasn't blown up.
    expect($newResp)->toBe($response);

});

