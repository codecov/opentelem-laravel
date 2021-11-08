<?php

declare(strict_types=1);

namespace Codecov\LaravelCodecovOpenTelemetry\Codecov;

use Codecov\LaravelCodecovOpenTelemetry\Exceptions\NoCodeException;
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
        string $name,
        string $host,
        string $authToken,
        ClientInterface $client = null,
        RequestFactoryInterface $requestFactory = null,
        StreamFactoryInterface $streamFactory = null,
        SpanConverter $spanConverter = null
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

    public function convertSpans(iterable $spans)
    {
        $convertedSpans = [];
        foreach ($spans as $span) {
            array_push($convertedSpans, $this->spanConverter->convert($span));
        }

        return $convertedSpans;
    }

    /**
     * Exports the provided Span data.
     *
     * @param iterable<API\Span> $spans Array of Spans
     *
     * @return int return code, defined on the Exporter interface
     */
    public function export(iterable $spans, bool $retry = true): int
    {
        if (!$this->running) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        }

        if (empty($spans)) {
            return Trace\Exporter::SUCCESS;
        }

        $convertedSpans = $this->convertSpans($spans);
        $version = config('laravel_codecov_opentelemetry.profiling_id');


        try {
            // Only set a profiler version if an identifier has been provided.
            // This will prevent a redundant api call if it isn't needed.
            // It is expected version support will change as the codecov api changes
            // to accommodate the stateless approach required by this package.

            $presignedURL = $this->getPresignedPut($this->authToken, $this->uploadsUrl, $version);


            $this->makeRequest(
                'PUT',
                $presignedURL,
                ['content-type' => 'application/txt'],
                ['spans' => $convertedSpans],
            );
        } catch (NoCodeException $e) {
            // We did not have a code. so we have to create one and try again.
            // Prevents an infinite recursion scenario that can happen if `profiling/versions` consistently
            // fails.
            if ($retry) {
                $version = $this->setProfilerVersion($this->authToken, config('laravel_codecov_opentelemetry.execution_environment'), $this->versionsUrl, $version);
                return $this->export($spans, false);
            }
        } catch (RequestExceptionInterface $e) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }

        return Trace\Exporter::SUCCESS;
    }

    public function setProfilerVersion(string $authToken, string $env, string $versionsUrl, ?string $version)
    {
        try {
            if (!$version) {
                // If we do not get a falsy version, we set to "NO_VERSION"
                // This is specifically different than "default" which is the value
                // we use when a user doesn't specify the version at all.
                $version = "NO_VERSION";
            }

            $env = config('laravel_codecov_opentelemetry.execution_environment');

            $response = $this->makeRequest(
                'POST',
                $versionsUrl,
                [
                    'content-type' => 'application/json',
                    'Authorization' => 'repotoken '.$authToken,
                    'Accept' => 'application/json',
                ],
                [
                    'version_identifier' => $version,
                    'environment' => $env,
                    'code' => $version,
                ]
            );

            return $response->external_id;
        } catch (RequestExceptionInterface $e) {
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return Trace\Exporter::FAILED_RETRYABLE;
        }
    }

    public function getPresignedPut(string $authToken, string $uploadsUrl, ?string $externalId = null)
    {
        try {
            if (!$externalId) {
                $externalId = 'default';
            }

            $response = $this->makeRequest(
                'POST',
                $uploadsUrl,
                [
                    'content-type' => 'application/json',
                    'Authorization' => 'repotoken '.$authToken,
                    'Accept' => 'application/json',
                ],
                [
                    'profiling' => $externalId,
                ]
            );

            return $response->raw_upload_location;
        } catch (RequestExceptionInterface $e) {
            $response = $e->getResponse();
            $responseBody = json_decode((string) $response->getBody()->getContents());
            if ($responseBody->profiling) {
                foreach ($responseBody->profiling as $errorMsg) {
                    if ($errorMsg == 'Object with code='.$externalId.' does not exist.') {
                        throw new NoCodeException('Profile version with code '.$externalId.' does not exist.');
                    }
                }
            }

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

    public function makeRequest(string $type, string $url, array $headers, array $body)
    {
        $response = $this->client->request($type, $url, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return json_decode((string) $response->getBody());
    }
}
