<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Command;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Analytics\Service\AnalyticsDemoSeeder;
use App\Identity\Command\SeedDemoCommand;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\DemoIdentitySeeder;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueSampleSeeder;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Performance\Service\NPlusOneDetector;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectApiKeyFactory;
use App\Project\Service\ProjectFactory;
use App\Setup\Command\SeedSampleCommand;
use App\Setup\Demo\MercureSampleSeeder;
use App\Setup\Demo\SampleDataService;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SeedSampleCommandTest extends TestCase
{
    public function testFailsWhenProjectMissingAndWhenHugeWithoutForce(): void
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn(null);
        $tester = new CommandTester($this->command($projects));
        self::assertSame(1, $tester->execute([]));
        self::assertStringContainsString('not found', $tester->getDisplay());

        $project = (new Project())->setName('P')->setSlug(SeedDemoCommand::DEMO_PROJECT_SLUG);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 1);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn($project);
        $tester = new CommandTester($this->command($projects));
        self::assertSame(1, $tester->execute(['--size' => 'huge']));
        self::assertStringContainsString('--force', $tester->getDisplay());
    }

    public function testSeedsDevProfileAndPurges(): void
    {
        $project = (new Project())->setName('P')->setSlug(SeedDemoCommand::DEMO_PROJECT_SLUG);
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 1);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn($project);

        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findOneByProjectAndFingerprint')->willReturn(new Issue());
        $tx = $this->createStub(PerfTransactionRepository::class);
        $tx->method('findOneBy')->willReturn(new PerfTransaction());
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOneBy')->willReturn(new DailyProjectStat());

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $query = $this->createStub(Query::class);
        $query->method('getSingleScalarResult')->willReturn(2);
        $query->method('execute')->willReturn(2);
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('createQuery')->willReturn($query);
        $em->method('clear');
        $em->method('flush');
        $em->method('persist');

        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $sample = new SampleDataService(
            $em,
            $projects,
            new IssueSampleSeeder($em, $issues),
            new AnalyticsDemoSeeder($em, $stats),
            new PerformanceDemoSeeder($em, $tx, $stats, new NPlusOneDetector()),
            new MercureSampleSeeder(
                $settingsRepo,
                new ConfiguredMercure($settingsRepo, '', '', '', new MercureHubUrlGuard()),
                '',
                '',
                '',
            ),
        );

        $demo = new DemoIdentitySeeder(
            $this->createStub(UserRepository::class),
            $projects,
            $this->createStub(UserPasswordHasherInterface::class),
            new ProjectFactory($projects, new ProjectApiKeyFactory($em)),
            new ProjectApiKeyFactory($em),
        );

        $tester = new CommandTester(new SeedSampleCommand($sample, $demo));
        self::assertSame(0, $tester->execute(['--size' => 'dev']));
        self::assertStringContainsString('Sample size "dev"', $tester->getDisplay());

        self::assertSame(0, $tester->execute(['--purge' => true]));
        self::assertStringContainsString('Purged sample telemetry', $tester->getDisplay());
    }

    private function command(ProjectRepository $projects): SeedSampleCommand
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $sample = new SampleDataService(
            $em,
            $projects,
            new IssueSampleSeeder($em, $this->createStub(IssueRepository::class)),
            new AnalyticsDemoSeeder($em, $this->createStub(DailyProjectStatRepository::class)),
            new PerformanceDemoSeeder(
                $em,
                $this->createStub(PerfTransactionRepository::class),
                $this->createStub(DailyProjectStatRepository::class),
                new NPlusOneDetector(),
            ),
            new MercureSampleSeeder(
                $settingsRepo,
                new ConfiguredMercure($settingsRepo, '', '', '', new MercureHubUrlGuard()),
                '',
                '',
                '',
            ),
        );
        $demo = new DemoIdentitySeeder(
            $this->createStub(UserRepository::class),
            $projects,
            $this->createStub(UserPasswordHasherInterface::class),
            new ProjectFactory($projects, new ProjectApiKeyFactory($em)),
            new ProjectApiKeyFactory($em),
        );

        return new SeedSampleCommand($sample, $demo);
    }
}
