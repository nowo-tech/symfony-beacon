<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Shared\Doctrine\PidSqliteUrlEnvVarProcessor;
use PHPUnit\Framework\TestCase;

final class PidSqliteUrlEnvVarProcessorTest extends TestCase
{
    public function testAppendsPidToSqliteFileUrl(): void
    {
        $processor = new PidSqliteUrlEnvVarProcessor();
        $getEnv = static fn (string $name): string => 'sqlite:////dev/shm/symfony-beacon-phpunit.db';

        $url = $processor->getEnv('pid_sqlite', 'BEACON_TEST_DATABASE_URL', $getEnv);

        self::assertSame(
            'sqlite:////dev/shm/symfony-beacon-phpunit-'.getmypid().'.db',
            $url,
        );
    }

    public function testLeavesMemoryAndNonSqliteUrlsUntouched(): void
    {
        $processor = new PidSqliteUrlEnvVarProcessor();

        self::assertSame(
            'sqlite:///:memory:',
            $processor->getEnv('pid_sqlite', 'X', static fn (): string => 'sqlite:///:memory:'),
        );
        self::assertSame(
            'mysql://app:pass@database:3306/app',
            $processor->getEnv('pid_sqlite', 'X', static fn (): string => 'mysql://app:pass@database:3306/app'),
        );
    }

    public function testDoesNotDoubleSuffixPid(): void
    {
        $processor = new PidSqliteUrlEnvVarProcessor();
        $already = 'sqlite:////dev/shm/symfony-beacon-phpunit-99999.db';

        self::assertSame(
            $already,
            $processor->getEnv('pid_sqlite', 'X', static fn (): string => $already),
        );
    }
}
