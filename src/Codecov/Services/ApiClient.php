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


    public function __construct($client = null, $messageFactory = null, $asyncClient = null)
    {
        $this->asyncClient = $asyncClient ?? HttpAsyncClientDiscovery::find();
        $this->client = $client ?? HttpClientDiscovery::find();
        $this->messageFactory = $messageFactory ?? MessageFactoryDiscovery::find();
    }

    public function sendRequest(string $type, string $url, array $headers, ?array $body = null)
    {
        if ($body) {
            $request = $this->messageFactory->createRequest($type, $url, $headers, json_encode($body));
        } else {
            $request = $this->messageFactory->createRequest($type, $url, $headers);
        }

        $response = $this->client->sendRequest($request);
        return $response;
    }

    public function sendAsyncRequest(string $type, string $url, array $headers, array $body)
    {
        $response = $this->asyncClient->send($type, $url, $headers, json_encode($body));
        return json_decode((string) $response->getBody());
    }
}
