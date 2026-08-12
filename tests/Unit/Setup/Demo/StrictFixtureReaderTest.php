<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Setup\Demo\StrictFixtureReader;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StrictFixtureReaderTest extends TestCase
{
    public function testRequireHelpersAcceptValidShapes(): void
    {
        $reader = $this->reader();
        $source = [
            'obj' => ['a' => 1],
            'list' => ['x', 'y'],
            'str' => 'ok',
            'nullable' => null,
            'nullable_str' => 'yes',
            'int' => 3,
            'bool' => true,
            'strings' => ['a', 'b'],
            'i18n' => ['en' => 'Hello', 'es' => 'Hola'],
        ];

        self::assertSame(['a' => 1], $reader->exposeRequireArray($source, 'obj', 'root'));
        self::assertSame(['x', 'y'], $reader->exposeRequireList($source, 'list', 'root'));
        self::assertSame('ok', $reader->exposeRequireString($source, 'str', 'root'));
        self::assertNull($reader->exposeRequireNullableString($source, 'nullable', 'root'));
        self::assertSame('yes', $reader->exposeRequireNullableString($source, 'nullable_str', 'root'));
        self::assertSame(3, $reader->exposeRequireInt($source, 'int', 'root'));
        self::assertTrue($reader->exposeRequireBool($source, 'bool', 'root'));
        self::assertSame(['a', 'b'], $reader->exposeRequireStringList($source, 'strings', 'root'));
        self::assertSame(['en' => 'Hello', 'es' => 'Hola'], $reader->exposeRequireTranslations($source, 'i18n', 'root'));
    }

    public function testRequireHelpersRejectInvalidShapes(): void
    {
        $reader = $this->reader();
        $bad = ['list' => ['a' => 1], 'str' => 1, 'nullable' => 1, 'int' => '1', 'bool' => 1, 'strings' => [1], 'i18n' => ['en' => 1]];

        $this->expectException(InvalidArgumentException::class);
        $reader->exposeRequireList($bad, 'list', 'root');
    }

    public function testRequireStringListRejectsNonStrings(): void
    {
        $reader = $this->reader();
        $this->expectException(InvalidArgumentException::class);
        $reader->exposeRequireStringList(['strings' => ['ok', 2]], 'strings', 'root');
    }

    public function testRequireTranslationsRejectsNonStringMap(): void
    {
        $reader = $this->reader();
        $this->expectException(InvalidArgumentException::class);
        $reader->exposeRequireTranslations(['i18n' => ['en' => 1]], 'i18n', 'root');
    }

    private function reader(): object
    {
        return new class {
            use StrictFixtureReader;

            private const string FIXTURE_FILE = 'demo.json';

            /** @param array<mixed> $source */
            public function exposeRequireArray(array $source, string $key, string $context): array
            {
                return $this->requireArray($source, $key, $context);
            }

            /** @param array<mixed> $source */
            public function exposeRequireList(array $source, string $key, string $context): array
            {
                return $this->requireList($source, $key, $context);
            }

            /** @param array<mixed> $source */
            public function exposeRequireString(array $source, string $key, string $context): string
            {
                return $this->requireString($source, $key, $context);
            }

            /** @param array<mixed> $source */
            public function exposeRequireNullableString(array $source, string $key, string $context): ?string
            {
                return $this->requireNullableString($source, $key, $context);
            }

            /** @param array<mixed> $source */
            public function exposeRequireInt(array $source, string $key, string $context): int
            {
                return $this->requireInt($source, $key, $context);
            }

            /** @param array<mixed> $source */
            public function exposeRequireBool(array $source, string $key, string $context): bool
            {
                return $this->requireBool($source, $key, $context);
            }

            /** @param array<mixed> $source
             * @return list<string>
             */
            public function exposeRequireStringList(array $source, string $key, string $context): array
            {
                return $this->requireStringList($source, $key, $context);
            }

            /**
             * @param array<mixed> $source
             *
             * @return array<string, string>
             */
            public function exposeRequireTranslations(array $source, string $key, string $context): array
            {
                return $this->requireTranslations($source, $key, $context);
            }
        };
    }
}
