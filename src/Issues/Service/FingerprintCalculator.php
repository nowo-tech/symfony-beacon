<?php

declare(strict_types=1);

namespace App\Issues\Service;

/**
 * Builds a stable issue fingerprint from an Envelope event payload.
 */
final class FingerprintCalculator
{
    /**
     * @param array<string, mixed> $payload
     */
    public function calculate(array $payload): string
    {
        if (isset($payload['fingerprint']) && \is_array($payload['fingerprint']) && [] !== $payload['fingerprint']) {
            $parts = array_map(static fn (mixed $v): string => (string) $v, $payload['fingerprint']);

            return hash('sha256', implode('|', $parts));
        }

        $exception = $this->firstException($payload);
        if (null !== $exception) {
            $type = (string) ($exception['type'] ?? 'Error');
            $value = (string) ($exception['value'] ?? '');
            $frame = $this->topFrame($exception);
            $file = (string) ($frame['filename'] ?? $frame['abs_path'] ?? '');
            $function = (string) ($frame['function'] ?? '');
            $lineno = (string) ($frame['lineno'] ?? '');

            return hash('sha256', implode('|', [$type, $value, $file, $function, $lineno]));
        }

        $message = (string) ($payload['message'] ?? 'unknown');
        $culprit = (string) ($payload['culprit'] ?? '');

        return hash('sha256', $message.'|'.$culprit);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function title(array $payload): string
    {
        $exception = $this->firstException($payload);
        if (null !== $exception) {
            $type = (string) ($exception['type'] ?? 'Error');
            $value = (string) ($exception['value'] ?? '');

            return '' !== $value ? $type.': '.$value : $type;
        }

        return (string) ($payload['message'] ?? 'Untitled event');
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function culprit(array $payload): string
    {
        if (isset($payload['culprit']) && \is_string($payload['culprit']) && '' !== $payload['culprit']) {
            return $payload['culprit'];
        }

        $exception = $this->firstException($payload);
        $frame = null !== $exception ? $this->topFrame($exception) : [];
        $function = (string) ($frame['function'] ?? '');
        $file = (string) ($frame['filename'] ?? '');

        return '' !== $function ? $function : $file;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function firstException(array $payload): ?array
    {
        $values = $payload['exception']['values'] ?? null;
        if (!\is_array($values) || [] === $values || !\is_array($values[0])) {
            return null;
        }

        /** @var array<string, mixed> $first */
        $first = $values[0];

        return $first;
    }

    /**
     * @param array<string, mixed> $exception
     *
     * @return array<string, mixed>
     */
    private function topFrame(array $exception): array
    {
        $frames = $exception['stacktrace']['frames'] ?? null;
        if (!\is_array($frames) || [] === $frames) {
            return [];
        }

        $last = $frames[\count($frames) - 1];

        return \is_array($last) ? $last : [];
    }
}
