<?php

declare(strict_types=1);

namespace Codecov\LaravelCodecovOpenTelemetry\Codecov;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use InvalidArgumentException;
use OpenTelemetry\Sdk\Trace;
use OpenTelemetry\Trace as API;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;

/**
 * Class CodecovExporter - implements the export interface for data transfer to Codecov.
 */
class Exporter implements Trace\Exporter
{
    /**
     * @var string
     */
    private $endpointUrl;

    /**
     * @var SpanConverter
     */
    private $spanConverter;

    /**
     * @var bool
     */
    private $running = true;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $authToken;

    public function __construct(
        $name,
        string $endpointUrl,
        string $authToken,
        SpanConverter $spanConverter = null,
        ClientInterface $client = null
    ) {
        $parsedDsn = parse_url($endpointUrl);

        if (!is_array($parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN');
        }

        if (
            !isset($parsedDsn['scheme'])
            || !isset($parsedDsn['host'])
            || !isset($parsedDsn['path'])
        ) {
            throw new InvalidArgumentException('Endpoint should have scheme, host, and path');
        }

        $this->endpointUrl = $endpointUrl;
        $this->client = $client ?? new Client(['timeout' => 30]);
        $this->spanConverter = $spanConverter ?? new SpanConverter($name);
        $this->authToken = $authToken;
    }

    /**
     * Exports the provided Span data.
     *
     * @param iterable<API\Span> $spans Array of Spans
     *
     * @return int return code, defined on the Exporter interface
     */
    public function export(iterable $spans): int
    {
        if (!$this->running) {
            return Exporter::FAILED_NOT_RETRYABLE;
        }

        if (empty($spans)) {
            return Trace\Exporter::SUCCESS;
        }

        $convertedSpans = [];
        foreach ($spans as $span) {
            array_push($convertedSpans, $this->spanConverter->convert($span));
        }

        try {
            $json = json_encode($convertedSpans);
            $headers = [
                'content-type' => 'application/json',
                'Authorization' => $this->authToken,
            ];
            $request = new Request('POST', $this->endpointUrl, $headers, $json);
            $response = $this->client->sendRequest($request);
        } catch (RequestExceptionInterface $e) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }

        if ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        }

        if ($response->getStatusCode() >= 500 && $response->getStatusCode() < 600) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }

        return Trace\Exporter::SUCCESS;
    }

    public function shutdown(): void
    {
        $this->running = false;
    }
}
