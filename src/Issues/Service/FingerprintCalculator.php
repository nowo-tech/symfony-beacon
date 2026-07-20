<?php

declare(strict_types=1);

namespace App\Issues\Service;

/**
 * Builds a stable issue fingerprint from an Envelope event payload.
 *
 * Grouping prefers similarity over exact text:
 * - exception type + stack location (file/function), without fragile line numbers
 * - volatile tokens in messages (IDs, UUIDs, numbers) are normalized
 * - client-provided fingerprint arrays still win when present
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
            $value = $this->normalizeMessage((string) ($exception['value'] ?? ''));
            $frame = $this->topFrame($exception['stacktrace']['frames'] ?? null);
            $file = $this->normalizePath((string) ($frame['filename'] ?? $frame['abs_path'] ?? ''));
            $function = (string) ($frame['function'] ?? '');

            return hash('sha256', implode('|', [$type, $value, $file, $function]));
        }

        $message = $this->normalizeMessage((string) ($payload['message'] ?? 'unknown'));
        $frame = $this->topFrame($payload['stacktrace']['frames'] ?? null);
        $file = $this->normalizePath((string) ($frame['filename'] ?? $frame['abs_path'] ?? ''));
        $function = (string) ($frame['function'] ?? ($payload['culprit'] ?? ''));

        return hash('sha256', implode('|', [$message, $file, $function]));
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
        $frame = null !== $exception
            ? $this->topFrame($exception['stacktrace']['frames'] ?? null)
            : $this->topFrame($payload['stacktrace']['frames'] ?? null);
        $function = (string) ($frame['function'] ?? '');
        $file = (string) ($frame['filename'] ?? '');

        return '' !== $function ? $function : $file;
    }

    /**
     * Collapse volatile tokens so similar messages share a fingerprint.
     */
    public function normalizeMessage(string $message): string
    {
        $normalized = preg_replace(
            '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i',
            '<uuid>',
            $message,
        ) ?? $message;
        $normalized = preg_replace('/\b[0-9a-f]{32}\b/i', '<hex>', $normalized) ?? $normalized;
        $normalized = preg_replace('/\d+/', '<n>', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/', ' ', $normalized) ?? $normalized);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', $path);
        if (preg_match('#/(src|app|tests)/(.+)$#', $path, $matches)) {
            return $matches[1].'/'.$matches[2];
        }

        return basename($path);
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
     * Prefer the outermost in-app frame; fall back to the last frame.
     *
     * @return array<string, mixed>
     */
    private function topFrame(mixed $frames): array
    {
        if (!\is_array($frames) || [] === $frames) {
            return [];
        }

        $fallback = [];
        foreach (array_reverse($frames) as $frame) {
            if (!\is_array($frame)) {
                continue;
            }
            $fallback = $frame;
            if (!empty($frame['in_app'])) {
                return $frame;
            }
        }

        return $fallback;
    }
}
