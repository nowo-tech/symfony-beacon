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
        if (!$project->isIngestEnabled()) {
            return;
        }

        $now ??= new DateTimeImmutable();
        $eventEnvironment = ProjectThresholdRule::normalizeEnvironment($eventEnvironment);
        $eventReleaseVersion = ProjectThresholdRule::normalizeRelease($eventReleaseVersion);

        $updated = false;
        foreach ($this->thresholdRuleRepository->findEnabledByProject($project) as $rule) {
            if ($rule->isCooldownActive($now)) {
                continue;
            }
            if (!$this->matchesCurrentEvent($rule, $eventEnvironment, $eventReleaseVersion)) {
                continue;
            }

            $since = $now->modify(\sprintf('-%d minutes', $rule->getWindowMinutes()));
            $actualCount = $this->eventRepository->countReceivedSince(
                $project,
                $since,
                $rule->getEnvironment(),
                $rule->getReleaseVersion(),
            );

            if ($actualCount < $rule->getErrorCount()) {
                continue;
            }

            $this->notificationDispatcher->dispatchVolumeThreshold($project, $rule, $actualCount);
            $rule->markFired($now);
            $updated = true;
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
