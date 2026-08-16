<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use InvalidArgumentException;
use JsonException;

/**
 * Maps OTLP HTTP JSON ExportMetricsServiceRequest bodies to Beacon event payloads.
 *
 * v1 keeps only failure-like data points (error attributes / error-ish metric names)
 * so metrics feed the same Issue pipeline as OTLP logs and ERROR spans.
 *
 * @see https://opentelemetry.io/docs/specs/otlp/#otlphttp
 */
final class OtlpMetricsMapper implements OtlpSignalMapperInterface
{
    use OtlpAttributeCodec;
    public const int MAX_DATA_POINTS = 200;

    /**
     * @return list<array<string, mixed>>
     */
    public function mapToEventPayloads(string $jsonBody): array
    {
        try {
            $decoded = json_decode($jsonBody, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('invalid otlp json');
        }

        if (!\is_array($decoded)) {
            throw new InvalidArgumentException('invalid otlp json');
        }

        /** @var list<array<string, mixed>> $events */
        $events = [];

        OtlpResourceIterator::walk(
            $decoded,
            'resourceMetrics',
            'resource_metrics',
            'scopeMetrics',
            'scope_metrics',
            'metrics',
            'metrics',
            $this->attributesMap(...),
            function (array $resourceAttrs, array $metric) use (&$events): bool {
                $name = isset($metric['name']) && \is_string($metric['name']) ? $metric['name'] : '';
                foreach ($this->dataPoints($metric) as $point) {
                    if (\count($events) >= self::MAX_DATA_POINTS) {
                        return false;
                    }
                    if (!\is_array($point)) {
                        continue;
                    }
                    $payload = $this->mapDataPoint($point, $name, $resourceAttrs);
                    if (null !== $payload) {
                        $events[] = $payload;
                    }
                }

                return \count($events) < self::MAX_DATA_POINTS;
            },
        );

        return $events;
    }

    /**
     * @param array<string, mixed> $metric
     *
     * @return list<mixed>
     */
    private function dataPoints(array $metric): array
    {
        foreach (['gauge', 'sum', 'histogram', 'summary', 'exponentialHistogram', 'exponential_histogram'] as $kind) {
            $block = $metric[$kind] ?? null;
            if (!\is_array($block)) {
                continue;
            }
            $points = $block['dataPoints'] ?? $block['data_points'] ?? null;
            if (\is_array($points)) {
                return array_values($points);
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed>  $point
     * @param array<string, string> $resourceAttrs
     *
     * @return array<string, mixed>|null
     */
    private function mapDataPoint(array $point, string $metricName, array $resourceAttrs): ?array
    {
        $attrs = $this->attributesMap(\is_array($point['attributes'] ?? null) ? $point['attributes'] : []);
        if (!$this->isFailurePoint($metricName, $attrs)) {
            return null;
        }

        $errorType = $attrs['error.type'] ?? $attrs['exception.type'] ?? null;
        $errorMessage = $attrs['exception.message'] ?? $attrs['error.message'] ?? null;
        $displayName = '' !== trim($metricName) ? trim($metricName) : 'OTLP metric';
        $message = $errorMessage ?? $displayName;
        $timestamp = $this->nanoToUnix(
            $point['timeUnixNano'] ?? $point['time_unix_nano']
            ?? $point['startTimeUnixNano'] ?? $point['start_time_unix_nano'] ?? null,
        );

        $environment = $resourceAttrs['deployment.environment']
            ?? $resourceAttrs['deployment.environment.name']
            ?? $attrs['deployment.environment']
            ?? null;
        $release = $resourceAttrs['service.version'] ?? $attrs['service.version'] ?? null;
        $service = $resourceAttrs['service.name'] ?? $attrs['service.name'] ?? null;

        $value = $this->numericValue($point);

        $payload = [
            'event_id' => $this->stableEventId($metricName, $attrs, $message, $timestamp, $value),
            'message' => $message,
            'level' => 'error',
            'platform' => 'otlp',
            'timestamp' => $timestamp,
            'tags' => array_filter([
                'otel.service' => $service,
                'otel.metric' => $displayName,
            ], static fn (?string $v): bool => null !== $v && '' !== $v),
            'extra' => [
                'otlp' => true,
                'otlp.signal' => 'metrics',
                ...(null !== $value ? ['otlp.metric_value' => (string) $value] : []),
            ],
        ];

        if (null !== $environment && '' !== $environment) {
            $payload['environment'] = $environment;
        }
        if (null !== $release && '' !== $release) {
            $payload['release'] = $release;
        }

        $payload['exception'] = [
            'values' => [[
                'type' => $errorType ?? 'OtlpMetricError',
                'value' => $message,
                'stacktrace' => [
                    'frames' => [['filename' => 'otlp', 'function' => substr($displayName, 0, 120), 'lineno' => 0]],
                ],
            ]],
        ];

        return $payload;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function isFailurePoint(string $metricName, array $attrs): bool
    {
        if ($this->isFailureMetricName($metricName)) {
            return true;
        }

        if (isset($attrs['error.type']) && '' !== $attrs['error.type']) {
            return true;
        }

        foreach ($attrs as $key => $value) {
            if (str_starts_with($key, 'exception.') && '' !== $value) {
                return true;
            }
        }

        $status = $attrs['otel.status_code'] ?? null;
        if (null === $status) {
            return false;
        }

        $normalized = strtoupper($status);

        return 'ERROR' === $normalized
            || 'STATUS_CODE_ERROR' === $normalized
            || '2' === $status;
    }

    private function isFailureMetricName(string $name): bool
    {
        $lower = strtolower($name);
        if (str_contains($lower, '.errors') || str_contains($lower, '.error')) {
            return true;
        }

        return str_ends_with($lower, '_errors');
    }

    /**
     * @param array<string, mixed> $point
     */
    private function numericValue(array $point): int|float|null
    {
        foreach (['asDouble', 'as_double', 'asInt', 'as_int'] as $key) {
            if (isset($point[$key]) && is_numeric($point[$key])) {
                return str_contains($key, 'Int') || str_contains($key, 'int')
                    ? (int) $point[$key]
                    : (float) $point[$key];
            }
        }
        if (isset($point['count']) && is_numeric($point['count'])) {
            return (int) $point['count'];
        }
        if (isset($point['sum']) && is_numeric($point['sum'])) {
            return (float) $point['sum'];
        }

        return null;
    }

    /**
     * @param array<string, string> $attrs
     */
    private function stableEventId(string $metricName, array $attrs, string $message, float $timestamp, int|float|null $value): string
    {
        $seed = json_encode([
            $metricName,
            $attrs,
            $message,
            $timestamp,
            $value,
        ], \JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $seed), 0, 32);
    }
}
