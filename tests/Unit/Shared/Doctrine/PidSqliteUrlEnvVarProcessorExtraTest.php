<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Doctrine;

use App\Shared\Doctrine\PidSqliteUrlEnvVarProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

final class PidSqliteUrlEnvVarProcessorExtraTest extends TestCase
{
    public function testRejectsNonStringValues(): void
    {
        $processor = new PidSqliteUrlEnvVarProcessor();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must be a string');
        $processor->getEnv('pid_sqlite', 'X', static fn (): array => []);
    }

    public function testLeavesNonDatabaseSqlitePathsUntouched(): void
    {
        $processor = new PidSqliteUrlEnvVarProcessor();
        $url = 'sqlite:////dev/shm/symfony-beacon';

        self::assertSame($url, $processor->getEnv('pid_sqlite', 'X', static fn (): string => $url));
        self::assertSame(['pid_sqlite' => 'string'], PidSqliteUrlEnvVarProcessor::getProvidedTypes());
    }
}
