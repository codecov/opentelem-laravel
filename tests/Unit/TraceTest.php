<?php

use GuzzleHttp\Exception\RequestException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;

use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use OpenTelemetry\Sdk\Trace;
use Orchestra\Testbench\TestCase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    $traceMiddleware = new CodecovTrace;

    $newResp = $traceMiddleware->handle($request, function() use($response) {
        return $response;
    });

    //make sure we get our mocked response back and handle() hasn't blown up.
    expect($newResp)->toBe($response);

});