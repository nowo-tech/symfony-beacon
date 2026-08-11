<?php

declare(strict_types=1);

namespace App\Notifications\Service;

use App\Issues\Repository\EventRepository;
use App\Notifications\Entity\ProjectThresholdRule;
use App\Notifications\Repository\ProjectThresholdRuleRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Evaluates rolling error/fatal volume threshold rules after ingest.
 */
final readonly class VolumeThresholdEvaluator
{
    public function __construct(
        private ProjectThresholdRuleRepository $thresholdRuleRepository,
        private EventRepository $eventRepository,
        private NotificationDispatcher $notificationDispatcher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function evaluate(
        Project $project,
        ?string $eventEnvironment = null,
        ?string $eventReleaseVersion = null,
        ?DateTimeImmutable $now = null,
    ): void {
        $this->evaluateContexts(
            $project,
            [[$eventEnvironment, $eventReleaseVersion]],
            $now,
        );
    }

    /**
     * Evaluate once per unique environment/release pair (loads rules a single time).
     *
     * COUNT queries are batched by (environment, release, windowMinutes).
     *
     * @param list<array{0: ?string, 1: ?string}> $contexts
     */
    public function evaluateContexts(
        Project $project,
        array $contexts,
        ?DateTimeImmutable $now = null,
    ): void {
        if (!$project->isIngestEnabled() || [] === $contexts) {
            return;
        }

        $now ??= new DateTimeImmutable();
        $rules = $this->thresholdRuleRepository->findEnabledByProject($project);
        if ([] === $rules) {
            return;
        }

        /** @var array<int, true> $checkedRuleIds */
        $checkedRuleIds = [];
        /** @var array<string, array{environment: ?string, release: ?string, window: int, rules: list<ProjectThresholdRule>}> $countGroups */
        $countGroups = [];

        foreach ($contexts as [$eventEnvironment, $eventReleaseVersion]) {
            $eventEnvironment = ProjectThresholdRule::normalizeEnvironment($eventEnvironment);
            $eventReleaseVersion = ProjectThresholdRule::normalizeRelease($eventReleaseVersion);

            foreach ($rules as $rule) {
                $ruleId = $rule->getId();
                if (null !== $ruleId && isset($checkedRuleIds[$ruleId])) {
                    continue;
                }
                if ($rule->isCooldownActive($now)) {
                    continue;
                }
                if (!$this->matchesCurrentEvent($rule, $eventEnvironment, $eventReleaseVersion)) {
                    continue;
                }

                if (null !== $ruleId) {
                    $checkedRuleIds[$ruleId] = true;
                }

                $environment = $rule->getEnvironment();
                $release = $rule->getReleaseVersion();
                $window = $rule->getWindowMinutes();
                $groupKey = ($environment ?? '')."\0".($release ?? '')."\0".$window;
                if (!isset($countGroups[$groupKey])) {
                    $countGroups[$groupKey] = [
                        'environment' => $environment,
                        'release' => $release,
                        'window' => $window,
                        'rules' => [],
                    ];
                }
                $countGroups[$groupKey]['rules'][] = $rule;
            }
        }

        $updated = false;
        foreach ($countGroups as $group) {
            $since = $now->modify(\sprintf('-%d minutes', $group['window']));
            $actualCount = $this->eventRepository->countReceivedSince(
                $project,
                $since,
                $group['environment'],
                $group['release'],
            );

            foreach ($group['rules'] as $rule) {
                if ($actualCount < $rule->getErrorCount()) {
                    continue;
                }

                $this->notificationDispatcher->dispatchVolumeThreshold($project, $rule, $actualCount);
                $rule->markFired($now);
                $updated = true;
            }
        }

        if ($updated) {
            $this->entityManager->flush();
        }
    }

    private function matchesCurrentEvent(
        ProjectThresholdRule $rule,
        ?string $eventEnvironment,
        ?string $eventReleaseVersion,
    ): bool {
        $ruleEnvironment = $rule->getEnvironment();
        if (null !== $ruleEnvironment && $ruleEnvironment !== $eventEnvironment) {
            return false;
        }

        $ruleRelease = $rule->getReleaseVersion();

        return null === $ruleRelease || $ruleRelease === $eventReleaseVersion;
    }
}
