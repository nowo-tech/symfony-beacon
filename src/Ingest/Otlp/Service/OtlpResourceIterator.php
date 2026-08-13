<?php

declare(strict_types=1);

namespace App\Ingest\Otlp\Service;

/**
 * Shared iteration over OTLP resource → scope → records nesting.
 */
final class OtlpResourceIterator
{
    /**
     * @param array<string, mixed>                                                               $decoded
     * @param non-empty-string                                                                   $resourceKeyCamel
     * @param non-empty-string                                                                   $resourceKeySnake
     * @param non-empty-string                                                                   $scopeKeyCamel
     * @param non-empty-string                                                                   $scopeKeySnake
     * @param non-empty-string                                                                   $recordsKeyCamel
     * @param non-empty-string                                                                   $recordsKeySnake
     * @param callable(list<mixed>): array<string, string>                                       $mapAttributes
     * @param callable(array<string, string> $resourceAttrs, array<string, mixed> $record): bool $onRecord         return false to stop
     */
    public static function walk(
        array $decoded,
        string $resourceKeyCamel,
        string $resourceKeySnake,
        string $scopeKeyCamel,
        string $scopeKeySnake,
        string $recordsKeyCamel,
        string $recordsKeySnake,
        callable $mapAttributes,
        callable $onRecord,
    ): void {
        $resources = $decoded[$resourceKeyCamel] ?? $decoded[$resourceKeySnake] ?? [];
        if (!\is_array($resources)) {
            return;
        }

        foreach ($resources as $resourceBlock) {
            if (!\is_array($resourceBlock)) {
                continue;
            }

            /** @var list<mixed> $rawAttrs */
            $rawAttrs = \is_array($resourceBlock['resource']['attributes'] ?? null)
                ? $resourceBlock['resource']['attributes']
                : [];
            $resourceAttrs = $mapAttributes($rawAttrs);

            $scopes = $resourceBlock[$scopeKeyCamel] ?? $resourceBlock[$scopeKeySnake] ?? [];
            if (!\is_array($scopes)) {
                continue;
            }

            foreach ($scopes as $scopeBlock) {
                if (!\is_array($scopeBlock)) {
                    continue;
                }

                $records = $scopeBlock[$recordsKeyCamel] ?? $scopeBlock[$recordsKeySnake] ?? [];
                if (!\is_array($records)) {
                    continue;
                }

                foreach ($records as $record) {
                    if (!\is_array($record)) {
                        continue;
                    }

                    if (!$onRecord($resourceAttrs, $record)) {
                        return;
                    }
                }
            }
        }
    }

    private function __construct()
    {
    }
}
