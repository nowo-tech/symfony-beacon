<?php

declare(strict_types=1);

namespace App\Performance\Service;

/**
 * Detects N+1 query patterns from DB/http spans with repeated similar descriptions.
 */
final class NPlusOneDetector
{
    private const int MIN_REPEATS = 5;

    /**
     * @param list<array{op?: string, description?: string, span_id?: string}> $spans
     *
     * @return array{
     *     count: int,
     *     groups: list<array{op: string, pattern: string, repeats: int, span_ids: list<string>}>,
     *     candidate_span_ids: list<string>
     * }
     */
    public function detect(array $spans): array
    {
        $buckets = [];
        foreach ($spans as $span) {
            $op = (string) ($span['op'] ?? '');
            if (!$this->isDbLikeOp($op)) {
                continue;
            }
            $description = (string) ($span['description'] ?? '');
            $pattern = $this->normalizeQuery($description);
            if ('' === $pattern) {
                continue;
            }
            $key = $op.'|'.$pattern;
            $buckets[$key] ??= [
                'op' => $op,
                'pattern' => $pattern,
                'repeats' => 0,
                'span_ids' => [],
            ];
            ++$buckets[$key]['repeats'];
            $spanId = (string) ($span['span_id'] ?? '');
            if ('' !== $spanId) {
                $buckets[$key]['span_ids'][] = $spanId;
            }
        }

        $groups = [];
        $candidateIds = [];
        foreach ($buckets as $bucket) {
            if ($bucket['repeats'] < self::MIN_REPEATS) {
                continue;
            }
            $groups[] = $bucket;
            foreach ($bucket['span_ids'] as $id) {
                $candidateIds[] = $id;
            }
        }

        return [
            'count' => \count($groups),
            'groups' => $groups,
            'candidate_span_ids' => array_values(array_unique($candidateIds)),
        ];
    }

    private function isDbLikeOp(string $op): bool
    {
        $op = strtolower($op);

        return str_starts_with($op, 'db')
            || str_starts_with($op, 'sql')
            || str_contains($op, 'query')
            || 'http.client' === $op;
    }

    private function normalizeQuery(string $description): string
    {
        $normalized = preg_replace('/\b\d+\b/', '?', $description) ?? $description;
        $normalized = preg_replace("/'[^']*'/", '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }
}
