<?php


use Codecov\LaravelCodecovOpenTelemetry\Codecov\Services\ApiClient;
use Codecov\LaravelCodecovOpenTelemetry\Exceptions\NoCodeException;
use Http\Mock\Client as MockClient;
use Orchestra\Testbench\TestCase;
use Http\Discovery\MessageFactoryDiscovery;

uses(TestCase::class);

beforeEach(function () {
    $config = testConfig();
    config($config);
});


it('Can make a successful async GET request', function () {
    // mock the apiClient's underlying client
    $httpClient = Mockery::mock('asyncClient');
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();

    // mock httplug and ensure it has a loaded response.
    $mockClient = new MockClient();
    $mockClient->addResponse($responseMock);

    // create the request
    $messageFactory = MessageFactoryDiscovery::find();
    $request = $messageFactory->createRequest('GET', 'https://my.url', ['content-type' => 'text/plain']);

    $httpClient->shouldReceive('sendAsyncRequest')
        ->andReturn($mockClient->sendRequest($request))
    ;

    $apiClient = new ApiClient(null, null, $httpClient);
    $resp = $apiClient->sendRequest('GET', 'https://my.url', ['content-type' => 'text/plain'], []);

    // ensure what we get back from the mock client is what we expect.
    $this->assertSame($resp, $responseMock);
});


it('Can make a successful sync GET request', function () {
    // mock the apiClient's underlying client
    config(['laravel_codecov_opentelemetry.force_sync_requests' => true]);

    $httpClient = Mockery::mock('client');
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();

    // mock httplug and ensure it has a loaded response.
    $mockClient = new MockClient();
    $mockClient->addResponse($responseMock);

    // create the request
    $messageFactory = MessageFactoryDiscovery::find();
    $request = $messageFactory->createRequest('GET', 'https://my.url', ['content-type' => 'text/plain']);

    // send the request when underlying apiclient's $client is invoked.
    $httpClient->shouldReceive('sendRequest')
        ->andReturn($mockClient->sendRequest($request))
    ;

    $apiClient = new ApiClient($httpClient);
    $resp = $apiClient->sendRequest('GET', 'https://my.url', ['content-type' => 'text/plain'], []);

    // ensure what we get back from the mock client is what we expect.
    $this->assertSame($resp, $responseMock);
});

it('Can make a successful sync POST request', function () {
    config(['laravel_codecov_opentelemetry.force_sync_requests' => true]);
    $httpClient = Mockery::mock('client');
    $responseMock = Mockery::mock('Psr\Http\Message\ResponseInterface')->makePartial();

    $mockClient = new MockClient();
    $mockClient->addResponse($responseMock);

    //make the request
    $messageFactory = MessageFactoryDiscovery::find();
    $request = $messageFactory->createRequest('POST', 'https://my.url', ['content-type' => 'text/plain'], json_encode(['data' => 'post-data']));

    $httpClient->shouldReceive('sendRequest')
        ->andReturn($mockClient->sendRequest($request))
    ;

    $apiClient = new ApiClient($httpClient);
    $resp = $apiClient->sendRequest('GET', 'https://my.url', ['content-type' => 'text/plain'], ['data' => 'post-data']);
    $this->assertSame($resp, $responseMock);
});
