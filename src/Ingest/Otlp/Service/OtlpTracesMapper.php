<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

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
final class OtlpTracesMapper implements OtlpSignalMapperInterface
{
    use OtlpAttributeCodec;
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

        OtlpResourceIterator::walk(
            $decoded,
            'resourceSpans',
            'resource_spans',
            'scopeSpans',
            'scope_spans',
            'spans',
            'spans',
            $this->attributesMap(...),
            function (array $resourceAttrs, array $span) use (&$events): bool {
                if (\count($events) >= self::MAX_SPANS) {
                    return false;
                }

                $payload = $this->mapSpan($span, $resourceAttrs);
                if (null !== $payload) {
                    $events[] = $payload;
                }

                return \count($events) < self::MAX_SPANS;
            },
        );

        return $events;
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
