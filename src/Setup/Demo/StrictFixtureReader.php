<?php

declare(strict_types=1);

namespace App\Setup\Demo;

use InvalidArgumentException;

/**
 * Typed accessors for JSON demo fixture payloads (shared by kit seeders).
 */
trait StrictFixtureReader
{
    /**
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    private function requireArray(array $source, string $key, string $context): array
    {
        $value = $source[$key] ?? null;
        if (!\is_array($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be an object.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     *
     * @return list<mixed>
     */
    private function requireList(array $source, string $key, string $context): array
    {
        $value = $source[$key] ?? null;
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a list.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireString(array $source, string $key, string $context): string
    {
        $value = $source[$key] ?? null;
        if (!\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a string.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireNullableString(array $source, string $key, string $context): ?string
    {
        $value = $source[$key] ?? null;
        if (null !== $value && !\is_string($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a string or null.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireInt(array $source, string $key, string $context): int
    {
        $value = $source[$key] ?? null;
        if (!\is_int($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be an integer.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     */
    private function requireBool(array $source, string $key, string $context): bool
    {
        $value = $source[$key] ?? null;
        if (!\is_bool($value)) {
            throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a boolean.', self::FIXTURE_FILE, $context, $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     *
     * @return list<string>
     */
    private function requireStringList(array $source, string $key, string $context): array
    {
        $value = $this->requireList($source, $key, $context);
        foreach ($value as $entry) {
            if (!\is_string($entry)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to contain only strings.', self::FIXTURE_FILE, $context, $key));
            }
        }

        return $value;
    }

    /**
     * @param array<mixed> $source
     *
     * @return array<string, string>
     */
    private function requireTranslations(array $source, string $key, string $context): array
    {
        $value = $this->requireArray($source, $key, $context);
        $translations = [];
        foreach ($value as $locale => $translation) {
            if (!\is_string($locale) || !\is_string($translation)) {
                throw new InvalidArgumentException(\sprintf('Fixture "%s" expects %s.%s to be a string map.', self::FIXTURE_FILE, $context, $key));
            }
            $translations[$locale] = $translation;
        }

        return $translations;
    }
}
