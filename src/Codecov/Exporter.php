<?php

declare(strict_types=1);

namespace Codecov\LaravelCodecovOpenTelemetry\Codecov;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use InvalidArgumentException;
use OpenTelemetry\Sdk\Trace;
use OpenTelemetry\Trace as API;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class CodecovExporter - implements the export interface for data transfer to Codecov.
 */
class Exporter implements Trace\Exporter
{
    /**
     * @var string
     */
    private $uploadsUrl;

    /**
     * @var string
     */
    private $versionsUrl;

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
     * @var HttpFactory
     */
    private $requestFactory;

    /**
     * @var HttpFactory
     */
    private $streamFactory;

    /**
     * @var string
     */
    private $authToken;

    public function __construct(
        $name,
        string $host,
        string $authToken,
        ClientInterface $client = null,
        RequestFactoryInterface $requestFactory = null,
        StreamFactoryInterface $streamFactory = null,
        SpanConverter $spanConverter = null,
    ) {
        $parsedDsn = parse_url($host);

        if (!is_array($parsedDsn)) {
            throw new InvalidArgumentException('Unable to parse provided DSN');
        }

        if (
            !isset($parsedDsn['scheme'])
            || !isset($parsedDsn['host'])
        ) {
            throw new InvalidArgumentException('Endpoint should have scheme and host');
        }

        $this->uploadsUrl = $host.'/profiling/uploads';
        $this->versionsUrl = $host.'/profiling/versions';
        $this->client = $client ?? new Client(['timeout' => 30]);

        $this->requestFactory = $requestFactory ? $requestFactory : new HttpFactory();
        $this->streamFactory = $streamFactory ? $streamFactory : new HttpFactory();

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
            $version = $this->setProfilerVersion();
            $presignedURL = $this->getPresignedPut($version);
            $json = json_encode($convertedSpans);

            $response = $this->client->request(
                'PUT',
                $presignedURL,
                [
                    'headers' => ['content-type' => 'application/txt'],
                    'body' => json_encode([
                        'spans' => $json,
                    ]),
                ]
            );

            dd($json);
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

    public function setProfilerVersion()
    {
        try {
            $version = config('laravel_codecov_opentelemetry.profiling_id');
            $env = config('laravel_codecov_opentelemetry.execution_environment');

            $payload = [
                'version_identifier' => $version,
                'environment' => $env,
            ];

            $response = $this->client->request(
                'POST',
                $this->versionsUrl,
                [
                    'headers' => [
                        'content-type' => 'application/json',
                        'Authorization' => 'repotoken '.$this->authToken,
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($payload),
                ]
            );

            $response = json_decode((string) $response->getBody());

            return $response->external_id;
        } catch (RequestExceptionInterface $e) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }
    }

    public function getPresignedPut(?string $externalId = null)
    {
        try {
            if ($externalId) {
                $payload = [
                    'profiling' => $externalId,
                ];
            } else {
                $payload = [
                    'profiling' => 'default',
                ];
            }

            $response = $this->client->request(
                'POST',
                $this->uploadsUrl,
                [
                    'headers' => [
                        'content-type' => 'application/json',
                        'Authorization' => 'repotoken '.$this->authToken,
                        'Accept' => 'application/json',
                    ],
                    'body' => json_encode($payload),
                ]
            );

            $response = json_decode((string) $response->getBody());

            return $response->raw_upload_location;
        } catch (RequestExceptionInterface $e) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }
    }

    public function shutdown(): void
    {
        $this->running = false;
    }

    public static function fromConnectionString(string $endpointUrl, string $name, string $authToken, $args = null)
    {
        $factory = new HttpFactory();

        return new Exporter(
            $name,
            $endpointUrl,
            $authToken,
            new Client(),
            $factory,
            $factory
        );
    }
}
