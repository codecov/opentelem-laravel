<?php

declare(strict_types=1);

namespace Codecov\LaravelCodecovOpenTelemetry\Codecov;

use Codecov\LaravelCodecovOpenTelemetry\Exceptions\NoCodeException;
use Codecov\LaravelCodecovOpenTelemetry\Codecov\Services\ApiClient;
use Http\Discovery\Psr17FactoryDiscovery;
use InvalidArgumentException;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\ErrorFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\Trace as API;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class CodecovExporter - implements the export interface for data transfer to Codecov.
 */
class Exporter implements SpanExporterInterface
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
     * @var ApiClient
     */
    private $client;

    /**
     * @var RequestFactoryInterface
     */
    private $requestFactory;

    /**
     * @var StreamFactoryInterface
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
        $client = null,
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
        $this->client = $client ?? new ApiClient();

        $this->requestFactory = $requestFactory ? $requestFactory : Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ? $streamFactory : Psr17FactoryDiscovery::findStreamFactory();

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
    public function export(iterable $spans, ?CancellationInterface $cancellation = null): FutureInterface
    {
        try {
            return $this->exportSpans($spans);
        } catch (NoCodeException $e) {
            // We did not have a code. so we have to create one and try again.
            // Prevents an infinite recursion scenario that can happen if `profiling/versions` consistently
            // fails.
            $version = $this->setProfilerVersion($this->authToken, config('laravel_codecov_opentelemetry.execution_environment'), $this->versionsUrl, $version);
            return $this->exportSpans($spans);
        }
    }

    public function exportSpans(iterable $spans): FutureInterface
    {
        if (!$this->running) {
            return new CompletedFuture(false);
        }

        if (empty($spans)) {
            return new CompletedFuture(true);
        }

        $convertedSpans = $this->convertSpans($spans);
        $version = config('laravel_codecov_opentelemetry.profiling_id');


        try {
            // Only set a profiler version if an identifier has been provided.
            // This will prevent a redundant api call if it isn't needed.
            // It is expected version support will change as the codecov api changes
            // to accommodate the stateless approach required by this package.

            if ($this->client->isAsync()) {
                $this->performAsyncUpload($this->authToken, $this->uploadsUrl, $convertedSpans, $version);
            } else {
                $presignedURL = $this->getPresignedPut($this->authToken, $this->uploadsUrl, $version);

                $this->client->sendRequest(
                    'PUT',
                    $presignedURL,
                    ['content-type' => 'text/plain'],
                    ['spans' => $convertedSpans],
                );
            }
        } catch (RequestExceptionInterface $e) {
            return new ErrorFuture($e);
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return new ErrorFuture($e);
        }

				return new CompletedFuture(true);
    }

    public function performAsyncUpload(string $authToken, string $uploadsUrl, array $spanData, ?string $externalId = null)
    {
        //get a presigned PUT

        if (!$externalId) {
            $externalId = 'default';
        }

        $promise = $this->client->sendRequest(
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

        $promise->then(function (ResponseInterface $response) use ($externalId, $spanData) {
            //send request using the presigned PUT.
            $response = json_decode((string) $response->getBody());
            $this->checkForNoCode($response, $externalId);
            $presignedURL =  $response->raw_upload_location;

            // This will also be async, but we do not care about the result.
            // Fire and forget.
            $this->client->sendRequest(
                'PUT',
                $presignedURL,
                ['content-type' => 'text/plain'],
                ['spans' => $spanData],
            );
        }, function (RequestExceptionInterface $e) use ($externalId) {
            // here our attempt to get the presigned put failed.
            $response = $e->getResponse();
            $responseBody = json_decode((string) $response->getBody()->getContents());
            $this->checkForNoCode($responseBody, $externalId);
            return Trace\Exporter::FAILED_NOT_RETRYABLE;
        });

        //return the promise
        return $promise;
    }

    public function setProfilerVersion(string $authToken, string $env, string $versionsUrl, ?string $version)
    {
        try {
            if (!$version) {
                // If we do not get a falsy version, set to
                // "default" which is the value we use when
                // a user doesn't specify the version at all.
                $version = 'default';
            }

            $env = config('laravel_codecov_opentelemetry.execution_environment');

            // append a valid environment if it exists.
            // This will allow the same version to be used across environments.
            $code = $env ? $env . '-' . $version : $version;

            $response = $this->client->sendRequest(
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
                    'code' => $code,
                ]
            );

            $response = json_decode((string) $response->getBody());
            return $response->external_id;
        } catch (RequestExceptionInterface $e) {
            return false;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return false;
        }
    }

    public function getPresignedPut(string $authToken, string $uploadsUrl, ?string $externalId = null)
    {
        try {
            if (!$externalId) {
                $externalId = 'default';
            } else {
                //code is id + env
                $env = config('laravel_codecov_opentelemetry.execution_environment');
                $externalId = $env ? $env . '-' . $externalId : $externalId;
            }

            $response = $this->client->sendRequest(
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

            $response = json_decode((string) $response->getBody());
            $this->checkForNoCode($response, $externalId);
            return $response->raw_upload_location;
        } catch (RequestExceptionInterface $e) {
            $response = $e->getResponse();
            $responseBody = json_decode((string) $response->getBody()->getContents());
            $this->checkForNoCode($responseBody, $externalId);

            return false;
        } catch (NetworkExceptionInterface | ClientExceptionInterface $e) {
            return false;
        }
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        $this->running = false;
        return true;
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        $this->running = false;
        return true;
    }

    public static function fromConnectionString(string $endpointUrl, string $name, string $authToken, $args = null): Exporter
    {
        $factory = Psr17FactoryDiscovery::findRequestFactory();

        return new Exporter(
            $name,
            $endpointUrl,
            $authToken,
            new ApiClient(),
            $factory,
            $factory
        );
    }

    public function checkForNoCode(\stdClass $responseBody, string $externalId)
    {
        if (isset($responseBody->profiling) && is_array($responseBody->profiling)) {
            foreach ($responseBody->profiling as $errorMsg) {
                if ($errorMsg == 'Object with code='.$externalId.' does not exist.') {
                    throw new NoCodeException('Profile version with code '.$externalId.' does not exist.');
                }
            }
        }
    }
}
