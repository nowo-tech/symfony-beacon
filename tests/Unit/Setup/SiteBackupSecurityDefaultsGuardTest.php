<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup;

use App\Setup\SiteBackupSecurityDefaultsGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class SiteBackupSecurityDefaultsGuardTest extends TestCase
{
    public function testDevEnvironmentAllowsLocalDefaults(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'dev',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $guard->assertProductionSecretsSafe();
        $this->addToAssertionCount(1);
    }

    public function testTestEnvironmentAllowsLocalDefaults(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'test',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $guard->assertProductionSecretsSafe();
        $this->addToAssertionCount(1);
    }

    #[DataProvider('nonLocalEnvironmentsProvider')]
    public function testNonLocalRejectsEmptySetupToken(string $environment): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard($environment, '', '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SITE_SETUP_TOKEN must be set');
        $guard->assertProductionSecretsSafe();
    }

    #[DataProvider('nonLocalEnvironmentsProvider')]
    public function testNonLocalRejectsDocumentedLocalSetupToken(string $environment): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            $environment,
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('documented local default');
        $guard->assertProductionSecretsSafe();
    }

    #[DataProvider('nonLocalEnvironmentsProvider')]
    public function testNonLocalRejectsDocumentedLocalPanelHash(string $environment): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            $environment,
            'unique-production-setup-token',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('SITE_BACKUP_PASSWORD_HASH');
        $guard->assertProductionSecretsSafe();
    }

    #[DataProvider('nonLocalEnvironmentsProvider')]
    public function testNonLocalAcceptsRotatedSecrets(string $environment): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            $environment,
            'unique-production-setup-token',
            '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        );
        $guard->assertProductionSecretsSafe();
        $this->addToAssertionCount(1);
    }

    #[DataProvider('cacheBuildConsoleCommandsProvider')]
    public function testBuildAutoScriptConsoleCommandsSkipGuard(string $commandName): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'prod',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_PANEL_PASSWORD_HASH,
        );
        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn($commandName);
        $event = new ConsoleCommandEvent(
            $command,
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );
        $guard->onConsole($event);
        $this->addToAssertionCount(1);
    }

    public function testOtherConsoleCommandsStillEnforceGuard(): void
    {
        $guard = new SiteBackupSecurityDefaultsGuard(
            'prod',
            SiteBackupSecurityDefaultsGuard::LOCAL_DEV_SETUP_TOKEN,
            '$2y$12$notTheLocalDefaultHashxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        );
        $command = $this->createStub(Command::class);
        $command->method('getName')->willReturn('doctrine:migrations:migrate');
        $event = new ConsoleCommandEvent(
            $command,
            $this->createStub(InputInterface::class),
            $this->createStub(OutputInterface::class),
        );
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('documented local default');
        $guard->onConsole($event);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonLocalEnvironmentsProvider(): iterable
    {
        yield 'prod' => ['prod'];
        yield 'staging' => ['staging'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function cacheBuildConsoleCommandsProvider(): iterable
    {
        yield 'cache:clear' => ['cache:clear'];
        yield 'cache:warmup' => ['cache:warmup'];
        yield 'assets:install' => ['assets:install'];
    }
}
