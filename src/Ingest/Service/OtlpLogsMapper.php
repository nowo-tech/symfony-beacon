<?php

declare(strict_types=1);

namespace App\Ingest\Service;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;

/**
 * Maps OTLP HTTP JSON ExportLogsServiceRequest bodies to Beacon event payloads.
 *
 * @see https://opentelemetry.io/docs/specs/otlp/#otlphttp
 */
final class OtlpLogsMapper
{
    public const int MAX_RECORDS = 200;

    /** OTLP severityNumber: WARN=13, ERROR=17, FATAL=21. */
    public const int MIN_SEVERITY = 13;

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
        $resourceLogs = $decoded['resourceLogs'] ?? $decoded['resource_logs'] ?? [];
        if (!\is_array($resourceLogs)) {
            return [];
        }

        foreach ($resourceLogs as $resourceLog) {
            if (!\is_array($resourceLog) || \count($events) >= self::MAX_RECORDS) {
                break;
            }

            $resourceAttrs = $this->attributesMap(
                \is_array($resourceLog['resource']['attributes'] ?? null)
                    ? $resourceLog['resource']['attributes']
                    : [],
            );

            $scopeLogs = $resourceLog['scopeLogs'] ?? $resourceLog['scope_logs'] ?? [];
            if (!\is_array($scopeLogs)) {
                continue;
            }

            foreach ($scopeLogs as $scopeLog) {
                if (!\is_array($scopeLog) || \count($events) >= self::MAX_RECORDS) {
                    break 2;
                }

                $logRecords = $scopeLog['logRecords'] ?? $scopeLog['log_records'] ?? [];
                if (!\is_array($logRecords)) {
                    continue;
                }

                foreach ($logRecords as $record) {
                    if (!\is_array($record) || \count($events) >= self::MAX_RECORDS) {
                        break 3;
                    }

                    $payload = $this->mapRecord($record, $resourceAttrs);
                    if (null !== $payload) {
                        $events[] = $payload;
                    }
                }
            }
        }

        return $events;
    }

    /**
     * Build a scrubbed Envelope body (no DSN) from mapped event payloads.
     *
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
     * @param array<string, mixed>  $record
     * @param array<string, string> $resourceAttrs
     *
     * @return array<string, mixed>|null
     */
    private function mapRecord(array $record, array $resourceAttrs): ?array
    {
        $severityNumber = isset($record['severityNumber']) ? (int) $record['severityNumber'] : 0;
        if ($severityNumber > 0 && $severityNumber < self::MIN_SEVERITY) {
            return null;
        }

        $severityText = isset($record['severityText']) && \is_string($record['severityText'])
            ? strtoupper($record['severityText'])
            : '';
        if (0 === $severityNumber && '' !== $severityText) {
            if (\in_array($severityText, ['TRACE', 'DEBUG', 'INFO', 'INFORMATION'], true)) {
                return null;
            }
        }

        $level = $this->mapLevel($severityNumber, $severityText);
        $message = $this->extractBody($record['body'] ?? null);
        if ('' === $message) {
            $message = 'OTLP log';
        }

        $attrs = $this->attributesMap(\is_array($record['attributes'] ?? null) ? $record['attributes'] : []);
        $environment = $resourceAttrs['deployment.environment']
            ?? $resourceAttrs['deployment.environment.name']
            ?? $attrs['deployment.environment']
            ?? null;
        $release = $resourceAttrs['service.version'] ?? $attrs['service.version'] ?? null;
        $service = $resourceAttrs['service.name'] ?? $attrs['service.name'] ?? null;

        $exceptionType = $attrs['exception.type'] ?? $attrs['exception.type.name'] ?? null;
        $exceptionMessage = $attrs['exception.message'] ?? null;
        $exceptionStack = $attrs['exception.stacktrace'] ?? null;

        $timestamp = $this->nanoToUnix($record['timeUnixNano'] ?? $record['time_unix_nano'] ?? null);

        $payload = [
            'event_id' => $this->stableEventId($record, $message, $timestamp),
            'message' => $message,
            'level' => $level,
            'platform' => 'otlp',
            'timestamp' => $timestamp,
            'tags' => array_filter([
                'otel.service' => $service,
                'otel.severity' => '' !== $severityText ? $severityText : null,
            ], static fn (?string $v): bool => null !== $v && '' !== $v),
            'extra' => [
                'otlp' => true,
            ],
        ];

        if (null !== $environment && '' !== $environment) {
            $payload['environment'] = $environment;
        }
        if (null !== $release && '' !== $release) {
            $payload['release'] = $release;
        }

        $traceId = isset($record['traceId']) && \is_string($record['traceId']) ? $record['traceId'] : null;
        $spanId = isset($record['spanId']) && \is_string($record['spanId']) ? $record['spanId'] : null;
        if (null !== $traceId || null !== $spanId) {
            $payload['contexts'] = [
                'trace' => array_filter([
                    'trace_id' => $traceId,
                    'span_id' => $spanId,
                ]),
            ];
        }

        if (null !== $exceptionType || null !== $exceptionMessage || null !== $exceptionStack) {
            $payload['exception'] = [
                'values' => [[
                    'type' => $exceptionType ?? 'Exception',
                    'value' => $exceptionMessage ?? $message,
                    'stacktrace' => [
                        'frames' => $this->framesFromStacktrace($exceptionStack),
                    ],
                ]],
            ];
        }

        return $payload;
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

    private function extractBody(mixed $body): string
    {
        if (\is_string($body)) {
            return $body;
        }
        if (!\is_array($body)) {
            return '';
        }
        if (isset($body['stringValue']) && \is_string($body['stringValue'])) {
            return $body['stringValue'];
        }
        if (isset($body['string_value']) && \is_string($body['string_value'])) {
            return $body['string_value'];
        }

        return '';
    }

    private function mapLevel(int $severityNumber, string $severityText): string
    {
        if ($severityNumber >= 21 || \in_array($severityText, ['FATAL', 'CRITICAL'], true)) {
            return 'fatal';
        }
        if ($severityNumber >= 17 || 'ERROR' === $severityText) {
            return 'error';
        }
        if ($severityNumber >= 13 || \in_array($severityText, ['WARN', 'WARNING'], true)) {
            return 'warning';
        }

        return 'error';
    }

    private function nanoToUnix(mixed $nano): float
    {
        if (\is_int($nano) || (\is_string($nano) && ctype_digit($nano))) {
            return ((float) $nano) / 1_000_000_000.0;
        }

        return (float) new DateTimeImmutable()->format('U.u');
    }

    /**
     * @param array<string, mixed> $record
     */
    private function stableEventId(array $record, string $message, float $timestamp): string
    {
        $seed = json_encode([
            $record['timeUnixNano'] ?? $record['time_unix_nano'] ?? null,
            $record['traceId'] ?? null,
            $record['spanId'] ?? null,
            $message,
            $timestamp,
        ], \JSON_THROW_ON_ERROR);

        return substr(hash('sha256', $seed), 0, 32);
    }

    /**
     * @return list<array{filename: string, function: string, lineno: int}>
     */
    private function framesFromStacktrace(?string $stack): array
    {
        if (null === $stack || '' === trim($stack)) {
            return [['filename' => 'otlp', 'function' => 'log', 'lineno' => 0]];
        }

        $first = strtok(str_replace("\r\n", "\n", $stack), "\n") ?: 'otlp';

        return [['filename' => substr($first, 0, 240), 'function' => 'log', 'lineno' => 0]];
    }
}
