<?php

declare(strict_types=1);

namespace  Codecov\LaravelCodecovOpenTelemetry\Codecov;

use OpenTelemetry\Trace\Span;
use SebastianBergmann\CodeCoverage\Report\Cobertura;

class SpanConverter
{
    public const STATUS_CODE_TAG_KEY = 'status_code';
    public const STATUS_DESCRIPTION_TAG_KEY = 'status_description';
    /**
     * @var string
     */
    private $serviceName;

    public function __construct(string $serviceName)
    {
        $this->serviceName = $serviceName;
    }

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

        $coverage = [];

        foreach ($span->getAttributes() as $k => $v) {
            if ('codecov.type' == $k) {
                // Push to codecov specific section of span
                $coverage['type'] = $this->sanitizeTagValue($v->getValue());
            } elseif ('codecov.coverage' == $k) {
                // Push to codecov specific section of span
                //convert the coverage object to cobertura, then base64 encode.
                $cobertura = (new Cobertura())->process($v->getValue());
                $cobertura = $this->sanitizeTagValue($cobertura);
                $coverage['coverage'] = base64_encode($cobertura);
            } else {
                // Push to general attributes
                $row['attributes'][$k] = $this->sanitizeTagValue($v->getValue());
            }
        }

        if ($coverage) {
            $row['codecov'] = $coverage;
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
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return join(',', array_map([$this, 'sanitizeTagValue'], $value));
        }

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
