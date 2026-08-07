<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

use DateTimeImmutable;

/**
 * Shared OTLP attribute / timestamp / envelope helpers for Logs, Traces, and Metrics mappers.
 */
trait OtlpAttributeCodec
{
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
}
