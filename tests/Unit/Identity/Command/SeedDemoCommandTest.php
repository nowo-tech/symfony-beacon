<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Command;

use App\Identity\Command\SeedDemoCommand;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\DemoIdentitySeeder;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectFactory;
use App\Setup\Demo\BreadcrumbDemoSeeder;
use App\Setup\Demo\CookieConsentDemoSeeder;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Setup\Demo\DemoFixtureLoader;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nowo\BreadcrumbKitBundle\Repository\BreadcrumbCollectionRepository;
use Nowo\CookieConsentBundle\Repository\CookieConsentConfigRepository;
use Nowo\CookieConsentBundle\Repository\CookieDefinitionRepository;
use Nowo\DashboardMenuBundle\Repository\MenuRepository;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedDemoCommandTest extends TestCase
{
    public function testBlocksOutsideLocalWithoutAllowFlag(): void
    {
        $tester = new CommandTester($this->command(environment: 'prod'));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('blocked', $tester->getDisplay());
    }

    public function testSeedsDemoInTestEnvironment(): void
    {
        $projectDir = sys_get_temp_dir().'/beacon-seed-demo-'.bin2hex(random_bytes(4));
        mkdir($projectDir.'/var/site-backup', 0775, true);

        $savedUsers = [];
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(static function (User $user) use (&$savedUsers): void {
            new ReflectionProperty(User::class, 'id')->setValue($user, 1);
            $savedUsers[] = $user;
        });
        $users->method('findAll')->willReturnCallback(static fn (): array => $savedUsers);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $projects->method('save');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');
        $em = $this->createStub(EntityManagerInterface::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $demo = new DemoIdentitySeeder($users, $projects, $hasher, new ProjectFactory($projects, $apiKeys), $apiKeys);

        $settings = InstanceSettings::defaults();
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $settingsRepo->method('save');

        $loader = new DemoFixtureLoader();
        $menuRepo = $this->createStub(MenuRepository::class);
        $menuRepo->method('findOneByCodeAndContext')->willReturn(null);
        $menuRepo->method('reset');
        $breadcrumbs = $this->createStub(BreadcrumbCollectionRepository::class);
        $breadcrumbs->method('findOneByCodeAndContextKey')->willReturn(null);
        $cookieConfigs = $this->createStub(CookieConsentConfigRepository::class);
        $cookieConfigs->method('findDefaultEnabled')->willReturn(null);
        $cookieDefs = $this->createStub(CookieDefinitionRepository::class);
        $cookieDefs->method('findByConfigOrdered')->willReturn([]);
        $em->method('persist');
        $em->method('flush');
        $em->method('remove');

        $command = new SeedDemoCommand(
            $demo,
            new BreadcrumbDemoSeeder($em, $breadcrumbs, $loader),
            new DashboardMenuDemoSeeder($em, $menuRepo, $loader),
            new CookieConsentDemoSeeder($em, $cookieConfigs, $cookieDefs, $loader),
            $settingsRepo,
            $projectDir,
            'test',
        );

        $tester = new CommandTester($command);
        self::assertSame(0, $tester->execute(['--write-client-env' => '']));
        self::assertStringContainsString('Created user', $tester->getDisplay());
        self::assertStringContainsString('UI DSN:', $tester->getDisplay());
        self::assertTrue($settings->isSetupCompleted());

        // cleanup
        @unlink($projectDir.'/var/site-backup/setup.done');
        @rmdir($projectDir.'/var/site-backup');
        @rmdir($projectDir.'/var');
        @rmdir($projectDir);
    }

    public function testWritesBeaconDsnWhenEmpty(): void
    {
        $projectDir = $this->tempProjectDirWithEnv("APP_ENV=test\nBEACON_DSN=\n");
        $tester = new CommandTester($this->commandWithSeeder($projectDir));
        self::assertSame(0, $tester->execute(['--write-client-env' => '']));
        self::assertStringContainsString('Set BEACON_DSN for server dogfooding', $tester->getDisplay());
        $env = (string) file_get_contents($projectDir.'/.env');
        self::assertMatchesRegularExpression('/^BEACON_DSN=http:\/\/'.preg_quote(SeedDemoCommand::DEMO_PUBLIC_KEY, '/').':/m', $env);
        $this->cleanupTempProjectDir($projectDir);
    }

    public function testLeavesNonEmptyBeaconDsnUnlessSyncFlag(): void
    {
        $stale = 'http://'.SeedDemoCommand::DEMO_PUBLIC_KEY.':'.SeedDemoCommand::DEMO_SECRET_KEY.'@127.0.0.1/00000000-0000-0000-0000-000000000000';
        $projectDir = $this->tempProjectDirWithEnv("APP_ENV=test\nBEACON_DSN={$stale}\n");
        $tester = new CommandTester($this->commandWithSeeder($projectDir));

        self::assertSame(0, $tester->execute(['--write-client-env' => '']));
        self::assertStringContainsString('left unchanged', $tester->getDisplay());
        self::assertStringContainsString($stale, (string) file_get_contents($projectDir.'/.env'));

        self::assertSame(0, $tester->execute([
            '--write-client-env' => '',
            '--sync-server-dsn' => true,
        ]));
        self::assertStringContainsString('Set BEACON_DSN for server dogfooding', $tester->getDisplay());
        $env = (string) file_get_contents($projectDir.'/.env');
        self::assertStringNotContainsString('00000000-0000-0000-0000-000000000000', $env);
        self::assertMatchesRegularExpression('/^BEACON_DSN=http:\/\/'.preg_quote(SeedDemoCommand::DEMO_PUBLIC_KEY, '/').':/m', $env);

        $this->cleanupTempProjectDir($projectDir);
    }

    private function tempProjectDirWithEnv(string $envContents): string
    {
        $projectDir = sys_get_temp_dir().'/beacon-seed-demo-'.bin2hex(random_bytes(4));
        mkdir($projectDir.'/var/site-backup', 0775, true);
        file_put_contents($projectDir.'/.env', $envContents);

        return $projectDir;
    }

    private function cleanupTempProjectDir(string $projectDir): void
    {
        @unlink($projectDir.'/.env');
        @unlink($projectDir.'/var/site-backup/setup.done');
        @rmdir($projectDir.'/var/site-backup');
        @rmdir($projectDir.'/var');
        @rmdir($projectDir);
    }

    private function commandWithSeeder(string $projectDir): SeedDemoCommand
    {
        $savedUsers = [];
        $users = $this->createStub(UserRepository::class);
        $users->method('findOneByEmail')->willReturn(null);
        $users->method('save')->willReturnCallback(static function (User $user) use (&$savedUsers): void {
            new ReflectionProperty(User::class, 'id')->setValue($user, 1);
            $savedUsers[] = $user;
        });
        $users->method('findInstanceAdmins')->willReturnCallback(static fn (): array => $savedUsers);
        $users->method('findFirstInstanceAdmin')->willReturnCallback(static fn (): ?User => $savedUsers[0] ?? null);
        $users->method('findAll')->willReturnCallback(static fn (): array => $savedUsers);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $projects->method('save');

        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');
        $em = $this->createStub(EntityManagerInterface::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $demo = new DemoIdentitySeeder($users, $projects, $hasher, new ProjectFactory($projects, $apiKeys), $apiKeys);

        $settings = InstanceSettings::defaults();
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);
        $settingsRepo->method('save');

        $loader = new DemoFixtureLoader();

        return new SeedDemoCommand(
            $demo,
            new BreadcrumbDemoSeeder($em, $this->createStub(BreadcrumbCollectionRepository::class), $loader),
            new DashboardMenuDemoSeeder($em, $this->createStub(MenuRepository::class), $loader),
            new CookieConsentDemoSeeder(
                $em,
                $this->createStub(CookieConsentConfigRepository::class),
                $this->createStub(CookieDefinitionRepository::class),
                $loader,
            ),
            $settingsRepo,
            $projectDir,
            'test',
        );
    }

    private function command(string $environment): SeedDemoCommand
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $projects = $this->createStub(ProjectRepository::class);
        $apiKeys = new ProjectApiKeyFactory($em);
        $loader = new DemoFixtureLoader();

        return new SeedDemoCommand(
            new DemoIdentitySeeder(
                $this->createStub(UserRepository::class),
                $projects,
                $this->createStub(UserPasswordHasherInterface::class),
                new ProjectFactory($projects, $apiKeys),
                $apiKeys,
            ),
            new BreadcrumbDemoSeeder($em, $this->createStub(BreadcrumbCollectionRepository::class), $loader),
            new DashboardMenuDemoSeeder($em, $this->createStub(MenuRepository::class), $loader),
            new CookieConsentDemoSeeder(
                $em,
                $this->createStub(CookieConsentConfigRepository::class),
                $this->createStub(CookieDefinitionRepository::class),
                $loader,
            ),
            $this->createStub(InstanceSettingsRepository::class),
            sys_get_temp_dir(),
            $environment,
        );
    }
}
