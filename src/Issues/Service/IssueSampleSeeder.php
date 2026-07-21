<?php

declare(strict_types=1);

namespace App\Issues\Service;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Generates synthetic issues/events for local QA sample profiles.
 */
final readonly class IssueSampleSeeder
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private IssueRepository $issueRepository,
    ) {
    }

    /**
     * @return array{issues: int, events: int} counts created
     */
    public function seed(Project $project, int $issueCount, int $eventCount): array
    {
        $issueCount = max(0, $issueCount);
        $eventCount = max(0, $eventCount);
        if (0 === $issueCount) {
            return ['issues' => 0, 'events' => 0];
        }

        $eventsPerIssue = max(1, (int) ceil($eventCount / $issueCount));
        $issuesCreated = 0;
        $eventsCreated = 0;
        $now = new DateTimeImmutable();

        for ($i = 1; $i <= $issueCount; ++$i) {
            $fingerprint = hash('sha256', 'beacon-sample|'.$project->getId().'|'.$i);
            $existing = $this->issueRepository->findOneByProjectAndFingerprint($project, $fingerprint);
            if ($existing instanceof Issue) {
                continue;
            }

            $seen = $now->modify(\sprintf('-%d hours', $i % 72));
            $issue = new Issue();
            $issue->setProject($project);
            $issue->setFingerprint($fingerprint);
            $issue->setTitle(\sprintf('Sample error #%d: synthetic exception for QA', $i));
            $issue->setCulprit(\sprintf('App\\Sample\\Demo%d', $i % 20));
            $issue->setLevel(0 === $i % 7 ? 'fatal' : 'error');
            $issue->setFirstSeen($seen);
            $issue->setLastSeen($seen);
            $issue->setLastEnvironment(0 === $i % 2 ? 'prod' : 'staging');
            $issue->setLastRelease(\sprintf('1.0.%d', $i % 5));
            $this->entityManager->persist($issue);
            ++$issuesCreated;

            $toCreate = min($eventsPerIssue, max(0, $eventCount - $eventsCreated));
            for ($e = 0; $e < $toCreate; ++$e) {
                $event = new Event();
                $event->setIssue($issue);
                $event->setEventId(bin2hex(random_bytes(16)));
                $event->setEnvironment($issue->getLastEnvironment());
                $event->setReleaseVersion($issue->getLastRelease());
                $event->setPlatform('php');
                $event->setPhpVersion('8.5.0');
                $event->setSymfonyVersion('8.1.0');
                $event->setPayload([
                    'message' => $issue->getTitle(),
                    'level' => $issue->getLevel(),
                    'platform' => 'php',
                    'environment' => $issue->getLastEnvironment(),
                    'release' => $issue->getLastRelease(),
                    'sample' => true,
                ]);
                $eventAt = $seen->modify(\sprintf('-%d minutes', $e * 3));
                $event->setEventTimestamp($eventAt);
                $event->setReceivedAt($eventAt);
                $this->entityManager->persist($event);
                $issue->incrementEventCount();
                ++$eventsCreated;
            }

            if (0 === $i % 50) {
                $this->entityManager->flush();
                $this->entityManager->clear();
                $project = $this->entityManager->find(Project::class, $project->getId()) ?? $project;
            }
        }

        $this->entityManager->flush();

        return ['issues' => $issuesCreated, 'events' => $eventsCreated];
    }
}
