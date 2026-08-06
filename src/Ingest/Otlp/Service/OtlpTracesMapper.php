<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * Maps OTLP HTTP JSON ExportTraceServiceRequest bodies to Beacon event payloads.
 *
 * v1 keeps only ERROR spans (status ERROR and/or exception attributes) so traces
 * feed the same Issue pipeline as OTLP logs — not a full Performance waterfall.
 *
 * @see https://opentelemetry.io/docs/specs/otlp/#otlphttp
 */
final class OtlpTracesMapper
{
    public const int MAX_SPANS = 200;

    /** OTLP StatusCode: UNSET=0, OK=1, ERROR=2. */
    public const int STATUS_CODE_ERROR = 2;

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
        $resourceSpans = $decoded['resourceSpans'] ?? $decoded['resource_spans'] ?? [];
        if (!\is_array($resourceSpans)) {
            return [];
        }

        foreach ($resourceSpans as $resourceSpan) {
            if (!\is_array($resourceSpan) || \count($events) >= self::MAX_SPANS) {
                break;
            }

            $resourceAttrs = $this->attributesMap(
                \is_array($resourceSpan['resource']['attributes'] ?? null)
                    ? $resourceSpan['resource']['attributes']
                    : [],
            );

            $scopeSpans = $resourceSpan['scopeSpans'] ?? $resourceSpan['scope_spans'] ?? [];
            if (!\is_array($scopeSpans)) {
                continue;
            }

            foreach ($scopeSpans as $scopeSpan) {
                if (!\is_array($scopeSpan) || \count($events) >= self::MAX_SPANS) {
                    break 2;
                }

                $spans = $scopeSpan['spans'] ?? [];
                if (!\is_array($spans)) {
                    continue;
                }

                foreach ($spans as $span) {
                    if (!\is_array($span) || \count($events) >= self::MAX_SPANS) {
                        break 3;
                    }

                    $payload = $this->mapSpan($span, $resourceAttrs);
                    if (null !== $payload) {
                        $events[] = $payload;
                    }
                }
            }
        }

        return $events;
    }

    /**
     * @param list<array<string, mixed>> $payloads
     */
    public function toEnvelopeBody(array $payloads): string
    {
        $lines = [
            json_encode(['sdk' => ['name' => 'beacon-otlp'], 'sent_at' => new DateTimeImmutable()->format(\DATE_ATOM)], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES),
        ];

        foreach ($payloads as $payload) {
            $encoded = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
            $lines[] = json_encode(['type' => 'event', 'length' => \strlen($encoded)], \JSON_THROW_ON_ERROR);
            $lines[] = $encoded;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed>  $span
     * @param array<string, string> $resourceAttrs
     *
     * @return array<string, mixed>|null
     */
    private function mapSpan(array $span, array $resourceAttrs): ?array
    {
        $attrs = $this->attributesMap(\is_array($span['attributes'] ?? null) ? $span['attributes'] : []);
        $exceptionType = $attrs['exception.type'] ?? null;
        $exceptionMessage = $attrs['exception.message'] ?? null;
        $exceptionStack = $attrs['exception.stacktrace'] ?? null;
        $hasException = null !== $exceptionType || null !== $exceptionMessage || null !== $exceptionStack;

        if (!$this->isErrorSpan($span) && !$hasException) {
            return null;
        }

        $name = isset($span['name']) && \is_string($span['name']) && '' !== trim($span['name'])
            ? trim($span['name'])
            : 'OTLP span';
        $message = $exceptionMessage ?? $name;
        $timestamp = $this->nanoToUnix($span['endTimeUnixNano'] ?? $span['end_time_unix_nano']
            ?? $span['startTimeUnixNano'] ?? $span['start_time_unix_nano'] ?? null);

        $environment = $resourceAttrs['deployment.environment']
            ?? $resourceAttrs['deployment.environment.name']
            ?? $attrs['deployment.environment']
            ?? null;
        $release = $resourceAttrs['service.version'] ?? $attrs['service.version'] ?? null;
        $service = $resourceAttrs['service.name'] ?? $attrs['service.name'] ?? null;

        $statusMessage = $this->statusMessage($span);

        $payload = [
            'event_id' => $this->stableEventId($span, $message, $timestamp),
            'message' => $message,
            'level' => 'error',
            'platform' => 'otlp',
            'timestamp' => $timestamp,
            'tags' => array_filter([
                'otel.service' => $service,
                'otel.span' => $name,
            ], static fn (?string $v): bool => null !== $v && '' !== $v),
            'extra' => [
                'otlp' => true,
                'otlp.signal' => 'traces',
                ...array_filter([
                    'otlp.status_message' => $statusMessage,
                ], static fn (?string $v): bool => null !== $v && '' !== $v),
            ],
        ];

        if (null !== $environment && '' !== $environment) {
            $payload['environment'] = $environment;
        }
        if (null !== $release && '' !== $release) {
            $payload['release'] = $release;
        }

        $traceId = isset($span['traceId']) && \is_string($span['traceId'])
            ? $span['traceId']
            : (isset($span['trace_id']) && \is_string($span['trace_id']) ? $span['trace_id'] : null);
        $spanId = isset($span['spanId']) && \is_string($span['spanId'])
            ? $span['spanId']
            : (isset($span['span_id']) && \is_string($span['span_id']) ? $span['span_id'] : null);
        if (null !== $traceId || null !== $spanId) {
            $payload['contexts'] = [
                'trace' => array_filter([
                    'trace_id' => $traceId,
                    'span_id' => $spanId,
                ]),
            ];
        }

        if ($hasException || $this->isErrorSpan($span)) {
            $payload['exception'] = [
                'values' => [[
                    'type' => $exceptionType ?? 'OtlpSpanError',
                    'value' => $exceptionMessage ?? ($statusMessage ?? $name),
                    'stacktrace' => [
                        'frames' => $this->framesFromStacktrace($exceptionStack, $name),
                    ],
                ]],
            ];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $span
     */
    private function isErrorSpan(array $span): bool
    {
        $status = $span['status'] ?? null;
        if (!\is_array($status)) {
            return false;
        }

        $code = $status['code'] ?? null;
        if (\is_int($code) || (\is_string($code) && ctype_digit($code))) {
            return self::STATUS_CODE_ERROR === (int) $code;
        }
        if (\is_string($code)) {
            return 'STATUS_CODE_ERROR' === strtoupper($code) || 'ERROR' === strtoupper($code);
        }

        return false;
    }

    /**
     * @param array<string, mixed> $span
     */
    private function statusMessage(array $span): ?string
    {
        $status = $span['status'] ?? null;
        if (!\is_array($status)) {
            return null;
        }
        $message = $status['message'] ?? null;

        return \is_string($message) && '' !== $message ? $message : null;
    }

    /**
     * @param list<mixed> $attributes
     *
     * @return array<string, string>
     */
    private function attributesMap(array $attributes): array
    {
        $map = [];
        foreach ($attributes as $attr) {
            if (!\is_array($attr)) {
                continue;
            }
            $key = $attr['key'] ?? null;
            if (!\is_string($key) || '' === $key) {
                continue;
            }
            $value = $this->attributeValue($attr['value'] ?? null);
            if (null !== $value) {
                $map[$key] = $value;
            }
        }

        return $map;
    }

    private function attributeValue(mixed $value): ?string
    {
        if (!\is_array($value)) {
            return \is_scalar($value) ? (string) $value : null;
        }

        if (isset($value['stringValue']) && \is_string($value['stringValue'])) {
            return $value['stringValue'];
        }
        if (isset($value['string_value']) && \is_string($value['string_value'])) {
            return $value['string_value'];
        }
        if (isset($value['intValue'])) {
            return (string) $value['intValue'];
        }
        if (isset($value['doubleValue'])) {
            return (string) $value['doubleValue'];
        }
        if (isset($value['boolValue'])) {
            return $value['boolValue'] ? 'true' : 'false';
        }

        return null;
    }

    private function nanoToUnix(mixed $nano): float
    {
        if (\is_int($nano) || (\is_string($nano) && ctype_digit($nano))) {
            return ((float) $nano) / 1_000_000_000.0;
        }

        return (float) new DateTimeImmutable()->format('U.u');
    }

    /**
     * @param array<string, mixed> $span
     */
    private function stableEventId(array $span, string $message, float $timestamp): string
    {
        $seed = json_encode([
            $span['traceId'] ?? $span['trace_id'] ?? null,
            $span['spanId'] ?? $span['span_id'] ?? null,
            $span['endTimeUnixNano'] ?? $span['end_time_unix_nano'] ?? null,
            $message,
            $timestamp,
        ], \JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $seed), 0, 32);
    }

    /**
     * @return list<array{filename: string, function: string, lineno: int}>
     */
    private function framesFromStacktrace(?string $stack, string $spanName): array
    {
        if (null === $stack || '' === trim($stack)) {
            return [['filename' => 'otlp', 'function' => substr($spanName, 0, 120), 'lineno' => 0]];
        }

        $first = strtok(str_replace("\r\n", "\n", $stack), "\n") ?: 'otlp';

        return [['filename' => substr($first, 0, 240), 'function' => substr($spanName, 0, 120), 'lineno' => 0]];
    }
}
