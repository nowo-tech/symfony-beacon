<?php

declare(strict_types=1);

namespace App\Shared\Portability;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Shared JSON envelope bits for config export/import (schema, version, exported_at).
 *
 * Domain payloads (projects, appearance, instance flags) stay in their own portability services.
 */
final class ConfigPortabilityEnvelope
{
    private function __construct()
    {
    }

    /**
     * @return array{schema: string, version: int, exported_at: string}
     */
    public static function header(string $schema, int $version): array
    {
        return [
            'schema' => $schema,
            'version' => $version,
            'exported_at' => new DateTimeImmutable()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws InvalidArgumentException when schema does not match
     */
    public static function assertSchema(array $payload, string $expectedSchema): void
    {
        if (($payload['schema'] ?? null) !== $expectedSchema) {
            throw new InvalidArgumentException('invalid_schema');
        }
    }

    /**
     * Require an exact schema version (project bundle style).
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidArgumentException when version is missing, non-numeric, or mismatched
     */
    public static function assertExactVersion(array $payload, int $expectedVersion): void
    {
        $version = $payload['version'] ?? null;
        if (!\is_int($version) && (!\is_string($version) || !ctype_digit($version))) {
            throw new InvalidArgumentException('invalid_version');
        }
        if ($expectedVersion !== (int) $version) {
            throw new InvalidArgumentException('unsupported_version');
        }
    }

    /**
     * Require a version in [minVersion, maxVersion] (instance config style).
     *
     * @param array<string, mixed> $payload
     *
     * @throws InvalidArgumentException when version is out of range
     */
    public static function assertCompatibleVersion(array $payload, int $maxVersion, int $minVersion = 1): int
    {
        $version = (int) ($payload['version'] ?? 0);
        if ($version < $minVersion || $version > $maxVersion) {
            throw new InvalidArgumentException('unsupported_version');
        }

        return $version;
    }
}
