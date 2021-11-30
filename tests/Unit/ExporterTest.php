<?php

use Codecov\LaravelCodecovOpenTelemetry\Codecov\Exporter as CodecovExporter;
use Codecov\LaravelCodecovOpenTelemetry\Exceptions\NoCodeException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use OpenTelemetry\Sdk\Trace;
use Orchestra\Testbench\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $config = testConfig();
    config($config);
});

it('will throw an InvalidArgumentException on domains without scheme and host', function () {
    config(['laravel_codecov_opentelemetry.codecov_host' => 'my-invalid-url']);

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );
})->throws(InvalidArgumentException::class, 'Endpoint should have scheme and host');

it('will throw an InvalidArgumentException on unparseable domains', function () {
    config(['laravel_codecov_opentelemetry.codecov_host' => '/test/fail:123']);
    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );
})->throws(InvalidArgumentException::class, 'Unable to parse provided DSN');

it('will not export if it is not running', function () {
    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );

    $exporter->shutdown();
    $notRunning = $exporter->export([]);
    $this->assertEquals(Trace\Exporter::FAILED_NOT_RETRYABLE, $notRunning);
});

it('will terminate successfully if there are no spans to export', function () {
    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token')
    );

    $this->assertEquals(Trace\Exporter::SUCCESS, $exporter->export([]));
});

it('will set some version without a profiling_id', function () {
    config(['laravel_codecov_opentelemetry.profiling_id' => null]);

    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();
    $responseMock->shouldReceive('getBody')
                 ->andReturn((object) ['external_id' => 1]);

    $mockClient = Mockery::mock(ApiClient::class)->makePartial();
    $mockClient->shouldReceive('sendRequest')
        ->andReturn($responseMock);

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token'),
        $mockClient
    );


    $this->assertEquals($exporter->setProfilerVersion('abc123', 'local', 'https://profilingurl', config('laravel_codecov_opentelemetry.profiling_id')), 1);
});

it('properly sets a profiler version', function () {
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();
    $responseMock->shouldReceive('getBody')
                 ->andReturn((object) ['external_id' => 1]);

    $mockClient = Mockery::mock(ApiClient::class)->makePartial();
    $mockClient->shouldReceive('sendRequest')
        ->andReturn($responseMock);

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token'),
        $mockClient
    );

    $this->assertEquals($exporter->setProfilerVersion('abc123', 'local', 'https://profilingurl', 'abc'), 1);
});

it('can get a presigned PUT', function () {
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();
    $responseMock->shouldReceive('getBody')
                 ->andReturn((object) ['raw_upload_location' => 'my-location']);

    $mockClient = Mockery::mock(ApiClient::class)->makePartial();
    $mockClient->shouldReceive('sendRequest')
        ->andReturn($responseMock);

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token'),
        $mockClient
    );

    $this->assertEquals($exporter->getPresignedPut('abc123', 'https://profilingurl', 'abc'), 'my-location');
});

it('properly throws a NoCodeException', function () {
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();
    $responseMock->shouldReceive('getBody')
                 ->andReturn((object) ['raw_upload_location' => 'my-location']);

    $mockClient = Mockery::mock(ApiClient::class)->makePartial();
    $mockClient->shouldReceive('sendRequest')
        ->andThrow(new RequestException('{"profiling": ["Object with code=1 does not exist."]}', new GuzzleRequest('POST', 'test'), new GuzzleResponse(404, [], '{"profiling": ["Object with code=1 does not exist."]}')));

    $exporter = new CodecovExporter(
        config('laravel_codecov_opentelemetry.service_name'),
        config('laravel_codecov_opentelemetry.codecov_host'),
        config('laravel_codecov_opentelemetry.profiling_token'),
        $mockClient
    );

    $exporter->getPresignedPut('abc123', 'https://profilingurl', '1');
})->throws(NoCodeException::class);

it('can export spans', function () {
    $converterMock = Mockery::mock(SpanCoverter::class)->makePartial();

    $converterMock->shouldReceive('convert')
        ->andReturn(['data'])
    ;

    $mock = Mockery::mock(CodecovExporter::class)->makePartial();
    $mock->shouldReceive('export');
    $mock->shouldReceive('convertSpans')->andReturn($converterMock);

    $this->assertEquals($mock->export(['one' => 'some data']), Trace\Exporter::SUCCESS);
});
