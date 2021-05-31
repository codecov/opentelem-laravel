<?php

declare(strict_types=1);

namespace  Codecov\LaravelCodecovOpenTelemetry\Codecov;

use OpenTelemetry\Trace\Span;

class SpanConverter
{
    const STATUS_CODE_TAG_KEY = 'status_code';
    const STATUS_DESCRIPTION_TAG_KEY = 'status_description';
    /**
     * @var string
     */
    private $serviceName;

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
    }

    /**
     * {.
     * "name": "codecov.views.redirect_app",
     * "context": {
     * "trace_id": "0xe3a8230d9f94655a04bfa686796a3fe7",
     * "span_id": "0xff945291223e2717",
     * "trace_state": "[]"
     * },
     * "kind": "SpanKind.SERVER",
     * "parent_id": null,
     * "start_time": "2021-05-14T14:13:40.897911Z",
     * "end_time": "2021-05-14T14:13:57.589268Z",
     * "status": {
     * "status_code": "UNSET"
     * },
     * "attributes": {
     * "http.method": "GET",
     * "http.server_name": "testserver",
     * "http.scheme": "http",
     * "net.host.port": 80,
     * "http.url": "http://testserver/redirect_app/gh/codecov/codecov.io/settings",
     * "net.peer.ip": "127.0.0.1",
     * "http.flavor": "1.1"
     * },
     * "events": [],
     * "links": [],
     * "resource": {
     * "telemetry.sdk.language": "python",
     * "telemetry.sdk.name": "opentelemetry",
     * "telemetry.sdk.version": "1.2.0",
     * "service.name": "unknown_service"
     * }
     * }.
     */
    public function convert(Span $span)
    {
        $spanParent = $span->getParent();
        $duration = ($span->getEnd() - $span->getStart()) / 1e3;
        $row = [
            'name' => $span->getSpanName(),
            'context' => [
                'trace_id' => $span->getContext()->getTraceId(),
                'span_id' => $span->getContext()->getSpanId(),
                'trace_state' => $spanParent ? $spanParent->getTraceState() : null,
            ],
            'kind' => $this->spanKindIntToString($span->getSpanKind()),
            'parent_id' => $spanParent ? $spanParent->getSpanId() : null,
            'start_time' => ($span->getStartEpochTimeStamp()), // RealtimeClock in microseconds
            'end_time' => ($span->getStartEpochTimeStamp() + $duration), // RealtimeClock in microseconds
            'duration' => $duration, // Diff in microseconds
            'status' => [
                self::STATUS_CODE_TAG_KEY => $span->getStatus()->getCanonicalStatusCode(),
                self::STATUS_DESCRIPTION_TAG_KEY => $span->getStatus()->getStatusDescription(),
            ],
            'links' => [],
            'events' => [],
        ];

        foreach ($span->getAttributes() as $k => $v) {
            $row['attributes'][$k] = $this->sanitizeTagValue($v->getValue());
        }

        // opentelemetry php's getlinks method isn't implemented yet.
        // this throws an exception, for now links will just be an empty array
        // foreach ($span->getLinks() as $k => $v) {
        //     $row['links'][$k] = $this->sanitizeTagValue($v->getValue());
        // }

        foreach ($span->getEvents() as $event) {
            if (!array_key_exists('events', $row)) {
                $row['events'] = [];
            }
            $row['events'][] = [
                'timestamp' => (int) ($event->getTimestamp() / 1e3), // RealtimeClock in microseconds
                'value' => $event->getName(),
            ];
        }

        return $row;
    }

    private function sanitizeTagValue($value)
    {
        // Casting false to string makes an empty string
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Zipkin tags must be strings, but opentelemetry
        // accepts strings, booleans, numbers, and lists of each.
        if (is_array($value)) {
            return join(',', array_map([$this, 'sanitizeTagValue'], $value));
        }

        // Floats will lose precision if their string representation
        // is >=14 or >=17 digits, depending on PHP settings.
        // Can also throw E_RECOVERABLE_ERROR if $value is an object
        // without a __toString() method.
        // This is possible because OpenTelemetry\Trace\Span does not verify
        // setAttribute() $value input.
        return (string) $value;
    }

    private function spanKindIntToString(int $spanKind): string
    {
        $prefix = 'SpanKind.';

        switch ($spanKind) {
            case 0:
                return $prefix.'INTERNAL';

            case 1:
                return $prefix.'CLIENT';

            case 2:
                return $prefix.'SERVER';

            case 3:
                return $prefix.'PRODUCER';

            case 4:
                return $prefix.'CONSUMER';

            default:
                return $prefix.'UNKNOWN';
        }
    }
}
