<?php

declare(strict_types=1);

namespace App\Tests\Unit\Setup\Demo;

use App\Analytics\Entity\DailyProjectStat;
use App\Analytics\Repository\DailyProjectStatRepository;
use App\Analytics\Service\AnalyticsDemoSeeder;
use App\Identity\Command\SeedDemoCommand;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueSampleSeeder;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Performance\Service\NPlusOneDetector;
use App\Performance\Service\PerformanceDemoSeeder;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Setup\Demo\MercureSampleSeeder;
use App\Setup\Demo\SampleDataService;
use App\Shared\Mercure\ConfiguredMercure;
use App\Shared\Mercure\MercureHubUrlGuard;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class SampleDataServiceTest extends TestCase
{
    public function testResolveProjectUsesLegacyFallbackAndThrowsWhenMissing(): void
    {
        $legacy = new Project()->setName('Demo')->setSlug(SeedDemoCommand::LEGACY_DEMO_PROJECT_SLUG);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Project => ($criteria['slug'] ?? null) === SeedDemoCommand::LEGACY_DEMO_PROJECT_SLUG ? $legacy : null);

        $service = $this->service($projects);
        self::assertSame($legacy, $service->resolveProject(SeedDemoCommand::DEMO_PROJECT_SLUG));

        $empty = $this->createStub(ProjectRepository::class);
        $empty->method('findOneBy')->willReturn(null);
        $this->expectException(InvalidArgumentException::class);
        $this->service($empty)->resolveProject('missing');
    }

    public function testSeedUnknownProfileThrowsAndDevProfileRuns(): void
    {
        $project = new Project()->setName('P')->setSlug('p');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 9);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn($project);

        $service = $this->service($projects);
        try {
            $service->seed($project, 'nope');
            self::fail('expected');
        } catch (InvalidArgumentException) {
            self::assertTrue(true);
        }

        // Lightweight: use tiny window by calling seed with 'dev' but stub issue repo to skip creates via existing fps — still heavy.
        // Instead seed with profile after replacing only through public API — use load profile would be huge.
        // Seed 'dev' with issue seeder that finds all fingerprints existing → 0 issues created.
        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findOneByProjectAndFingerprint')->willReturn(new Issue());
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('flush');
        $em->method('persist');

        $tx = $this->createStub(PerfTransactionRepository::class);
        $tx->method('findOneBy')->willReturn(new PerfTransaction());
        $stats = $this->createStub(DailyProjectStatRepository::class);
        $stats->method('findOneBy')->willReturn(new DailyProjectStat());

        $settings = InstanceSettings::defaults();
        $settings->setMercureEnabled(true);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn($settings);

        $service = new SampleDataService(
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

        $result = $service->seed($project, 'dev');
        self::assertSame(0, $result['issues']);
        self::assertFalse($result['analytics']);
        self::assertFalse($result['performance']);
        self::assertFalse($result['mercure']);
    }

    private function service(ProjectRepository $projects): SampleDataService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());

        return new SampleDataService(
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
    }
}
