<?php

namespace Codecov\LaravelCodecovOpenTelemetry\Codecov\Services;

use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\HttpClientDiscovery;
use Http\Discovery\MessageFactoryDiscovery;

class ApiClient
{
    private $asyncClient;
    private $client;
    private $messageFactory;
    private bool $isAsync = false;

    public function __construct($client = null, $messageFactory = null, $asyncClient = null)
    {
        $this->asyncClient = $asyncClient ?? HttpAsyncClientDiscovery::find();

        if ($this->asyncClient && !config('laravel_codecov_opentelemetry.force_sync_requests')) {
            $this->isAsync = true;
        }

        $this->client = $client ?? HttpClientDiscovery::find();
        $this->messageFactory = $messageFactory ?? MessageFactoryDiscovery::find();
    }

    public function isAsync(): bool
    {
        return $this->isAsync;
    }

    public function sendRequest(string $type, string $url, array $headers, ?array $body = null)
    {
        if ($body) {
            $request = $this->messageFactory->createRequest($type, $url, $headers, json_encode($body));
        } else {
            $request = $this->messageFactory->createRequest($type, $url, $headers);
        }

        if ($this->isAsync()) {
            //attempt to send the request async.
            $promise = $this->asyncClient->sendAsyncRequest($request);
            return $promise;
        } else {
            $response = $this->client->sendRequest($request);
            return $response;
        }
    }
}
