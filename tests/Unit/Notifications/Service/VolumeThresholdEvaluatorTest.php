<?php

declare(strict_types=1);

namespace App\Tests\Unit\Notifications\Service;

use App\Issues\Repository\EventRepository;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Realtime\MemberIssueRealtimeNotifierInterface;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Repository\NotificationDigestBufferRepository;
use App\Notifications\Repository\ProjectThresholdRuleRepository;
use App\Notifications\Service\NotificationCircuitBreaker;
use App\Notifications\Service\NotificationDispatcher;
use App\Notifications\Service\NotificationPayloadBuilder;
use App\Notifications\Service\QuietHoursEvaluator;
use App\Notifications\Service\VolumeThresholdEvaluator;
use App\Project\Entity\Project;
use App\Shared\Settings\Entity\InstanceSettings;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use App\Shared\Settings\Service\InstanceOpsDefaults;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class VolumeThresholdEvaluatorTest extends TestCase
{
    /** @var list<ProjectThresholdRule> */
    private array $rules = [];
    private int $eventCount = 0;
    private ProjectThresholdRuleRepository&Stub $ruleRepository;
    private EventRepository&Stub $eventRepository;
    private MessageBusInterface&Stub $bus;
    private EntityManagerInterface&MockObject $entityManager;
    private VolumeThresholdEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->createStub(ProjectThresholdRuleRepository::class);
        $this->ruleRepository->method('findEnabledByProject')->willReturnCallback(fn (): array => $this->rules);
        $this->eventRepository = $this->createStub(EventRepository::class);
        $this->eventRepository->method('countReceivedSince')->willReturnCallback(fn (): int => $this->eventCount);
        $this->bus = $this->createStub(MessageBusInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $settingsRepo = $this->createStub(InstanceSettingsRepository::class);
        $settingsRepo->method('getOrCreate')->willReturn(InstanceSettings::defaults());
        $ops = new InstanceOpsDefaults($settingsRepo);

        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issues/1');
        $destinations = $this->createStub(NotificationDestinationRepository::class);
        $destinations->method('findEnabledByProject')->willReturn([]);

        $dispatcher = new NotificationDispatcher(
            $destinations,
            $this->createStub(NotificationDigestBufferRepository::class),
            new NotificationPayloadBuilder($urls),
            new QuietHoursEvaluator(),
            new NotificationCircuitBreaker($ops),
            $this->bus,
            $this->entityManager,
            $this->createStub(MemberIssueRealtimeNotifierInterface::class),
        );

        $this->evaluator = new VolumeThresholdEvaluator(
            $this->ruleRepository,
            $this->eventRepository,
            $dispatcher,
            $this->entityManager,
        );
    }

    public function testSkipsWhenIngestDisabled(): void
    {
        $this->rules = [$this->rule(1, 1, 10)];
        $this->entityManager->expects(self::never())->method('flush');
        $this->evaluator->evaluate((new Project())->setIngestEnabled(false), 'prod', '1.0.0');
    }

    public function testSkipsWhenContextsEmpty(): void
    {
        $this->rules = [$this->rule(1, 1, 10)];
        $this->entityManager->expects(self::never())->method('flush');
        $this->evaluator->evaluateContexts((new Project())->setIngestEnabled(true), []);
    }

    public function testSkipsWhenNoEnabledRules(): void
    {
        $this->rules = [];
        $this->entityManager->expects(self::never())->method('flush');
        $this->evaluator->evaluate((new Project())->setIngestEnabled(true), 'prod', '1.0.0');
    }

    public function testFiresWhenCountMeetsThresholdAndFlushes(): void
    {
        $project = (new Project())->setIngestEnabled(true)->setName('P')->setSlug('p');
        $now = new DateTimeImmutable('2026-08-13T12:00:00+00:00');
        $rule = $this->rule(1, errorCount: 5, window: 15)->setProject($project);
        $this->rules = [$rule];
        $this->eventCount = 5;
        $this->entityManager->expects(self::exactly(2))->method('flush');

        $this->evaluator->evaluate($project, null, null, $now);

        self::assertSame($now, $rule->getLastFiredAt());
    }

    public function testSkipsCooldownAndEnvironmentMismatchAndBelowThreshold(): void
    {
        $project = (new Project())->setIngestEnabled(true);
        $now = new DateTimeImmutable('2026-08-13T12:00:00+00:00');
        $cooling = $this->rule(1, 1, 10)->setCooldownMinutes(60)->markFired($now->modify('-5 minutes'));
        $envMismatch = $this->rule(2, 1, 10)->setEnvironment('staging');
        $below = $this->rule(3, 100, 10);
        $this->rules = [$cooling, $envMismatch, $below];
        $this->eventCount = 2;
        $this->entityManager->expects(self::never())->method('flush');

        $this->evaluator->evaluate($project, 'production', '1.0.0', $now);
        self::assertNull($below->getLastFiredAt());
    }

    public function testDedupesRuleAcrossContextsAndBatchesSameWindow(): void
    {
        $project = (new Project())->setIngestEnabled(true)->setName('P')->setSlug('p');
        $now = new DateTimeImmutable('2026-08-13T12:00:00+00:00');
        $rule = $this->rule(7, 3, 20)->setEnvironment('prod')->setProject($project);
        $this->rules = [$rule];
        $this->eventCount = 10;
        $this->entityManager->expects(self::exactly(2))->method('flush');

        $this->evaluator->evaluateContexts(
            $project,
            [
                ['prod', '1.0.0'],
                ['prod', '1.0.1'],
            ],
            $now,
        );
        self::assertSame($now, $rule->getLastFiredAt());
    }

    private function rule(int $id, int $errorCount, int $window): ProjectThresholdRule
    {
        $rule = (new ProjectThresholdRule())
            ->setErrorCount($errorCount)
            ->setWindowMinutes($window)
            ->setEnabled(true);
        (new ReflectionProperty(ProjectThresholdRule::class, 'id'))->setValue($rule, $id);

        return $rule;
    }
}
