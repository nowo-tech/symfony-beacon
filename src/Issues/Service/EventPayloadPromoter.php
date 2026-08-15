<?php

declare(strict_types=1);

namespace App\Issues\Service;

/**
 * Extracts promoted filter fields from an Envelope event payload.
 */
final class EventPayloadPromoter
{
    private const int MAX_TAG_KEY = 120;
    private const int MAX_TAG_VALUE = 255;
    private const int MAX_TAGS = 40;
    private const int MAX_REQUEST_URL = 512;

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<array{key: string, value: string}>
     */
    public function extractTags(array $payload): array
    {
        $tags = $payload['tags'] ?? null;
        if (!\is_array($tags)) {
            return [];
        }

        $out = [];
        foreach ($tags as $key => $value) {
            if (\count($out) >= self::MAX_TAGS) {
                break;
            }
            if (!\is_string($key) && !\is_int($key)) {
                continue;
            }
            $tagKey = substr(trim((string) $key), 0, self::MAX_TAG_KEY);
            if ('' === $tagKey) {
                continue;
            }
            if (\is_bool($value)) {
                $tagValue = $value ? 'true' : 'false';
            } elseif (\is_scalar($value)) {
                $tagValue = substr(trim((string) $value), 0, self::MAX_TAG_VALUE);
            } else {
                continue;
            }
            if ('' === $tagValue) {
                continue;
            }
            $out[] = ['key' => $tagKey, 'value' => $tagValue];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function extractRequestUrl(array $payload): ?string
    {
        $request = $payload['request'] ?? null;
        if (!\is_array($request)) {
            $contexts = $payload['contexts'] ?? null;
            $request = \is_array($contexts) ? ($contexts['request'] ?? null) : null;
        }
        if (!\is_array($request)) {
            return null;
        }

        foreach (['url', 'uri'] as $key) {
            if (isset($request[$key]) && \is_scalar($request[$key]) && '' !== (string) $request[$key]) {
                return substr((string) $request[$key], 0, self::MAX_REQUEST_URL);
            }
        }

        return null;
    }
}
