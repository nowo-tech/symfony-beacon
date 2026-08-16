<?php

declare(strict_types=1);

namespace App\Ops\Service;

use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Notifications\Repository\PushSubscriptionRepository;
use App\Notifications\Service\WebPushClientFactory;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Explains why a successful ingest ACK may still produce no dashboard / Web Push alert.
 *
 * {@see \Nowo\BeaconBundle\Command\TestConnectionCommand} only proves Envelope auth + HTTP ACK.
 * Member alerts fire on new/regression issues after Messenger processes the Envelope, and Web Push
 * additionally needs VAPID + browser subscriptions.
 *
 * Issue novelty is inferred from the persisted issue's {@see Issue::getEventCount()} (BeaconBundle
 * message events include a stacktrace, so a host-side fingerprint of the message alone would not match).
 */
final readonly class BeaconDogfoodDiagnostics
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private EventRepository $eventRepository,
        private PushSubscriptionRepository $pushSubscriptionRepository,
        private WebPushClientFactory $webPushClientFactory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Build operator warnings after a successful Envelope ACK (or for `--check-only` push readiness).
     */
    public function diagnose(
        string $projectRef,
        ?string $eventId,
        bool $checkOnly,
        int $waitSeconds = 10,
    ): BeaconDogfoodDiagnosticReport {
        $project = $this->projectRepository->findOneByIngestPath($projectRef);
        $vapidConfigured = $this->webPushClientFactory->isConfigured();
        $pushCount = $this->pushSubscriptionRepository->countAll();

        $warnings = [];
        $notes = [];

        if (!$project instanceof Project) {
            $warnings[] = 'DSN project id was not found in this database — post-ACK issue/push checks were skipped.';

            return new BeaconDogfoodDiagnosticReport(
                projectFound: false,
                projectName: null,
                vapidConfigured: $vapidConfigured,
                pushSubscriptionCount: $pushCount,
                priorIssueExisted: false,
                priorEventCount: null,
                eventPersisted: false,
                issueUuid: null,
                issueEventCount: null,
                warnings: $warnings,
                notes: $this->pushReadinessNotes($vapidConfigured, $pushCount),
            );
        }

        $notes[] = 'Project: '.$project->getName().' ('.$project->getUuid().')';

        if ($checkOnly) {
            $notes = array_merge($notes, $this->pushReadinessNotes($vapidConfigured, $pushCount));
            if (!$vapidConfigured) {
                $warnings[] = 'Web Push VAPID keys are not configured — browser push cannot be sent.';
            }
            if (0 === $pushCount) {
                $warnings[] = 'No push_subscription rows yet — open the UI, enable alerts, and allow notifications in the browser.';
            }

            return new BeaconDogfoodDiagnosticReport(
                projectFound: true,
                projectName: $project->getName(),
                vapidConfigured: $vapidConfigured,
                pushSubscriptionCount: $pushCount,
                priorIssueExisted: false,
                priorEventCount: null,
                eventPersisted: false,
                issueUuid: null,
                issueEventCount: null,
                warnings: $warnings,
                notes: $notes,
            );
        }

        $notes[] = 'Probe level is info (BeaconBundle connection test). Webhook destinations often omit info; member alerts only fire for new/regression issues.';

        $event = null;
        if (null !== $eventId && '' !== $eventId) {
            $event = $this->waitForEvent($eventId, max(0, $waitSeconds));
        }

        $issueUuid = null;
        $issueEventCount = null;
        $priorIssueExisted = false;
        $priorEventCount = null;
        $eventPersisted = $event instanceof Event;

        if ($eventPersisted) {
            $issue = $event->getIssue();
            if ($issue instanceof Issue) {
                $issueUuid = $issue->getUuid();
                $issueEventCount = $issue->getEventCount();
                $priorIssueExisted = $issueEventCount > 1;
                $priorEventCount = $priorIssueExisted ? $issueEventCount - 1 : 0;
                $notes[] = \sprintf(
                    'Persisted event %s → issue %s (events=%d).',
                    $event->getEventId(),
                    $issueUuid,
                    $issueEventCount,
                );
                if ($priorIssueExisted) {
                    $warnings[] = \sprintf(
                        'Issue already existed before this probe (uuid=%s, events=%d) — ingest did not queue a “new issue” member alert.',
                        $issueUuid,
                        $issueEventCount,
                    );
                    $notes[] = 'Tip: pass --message=unique-probe-<token> so BeaconBundle builds a different stack fingerprint.';
                }
            }
        } else {
            $warnings[] = \sprintf(
                'Event id %s was not found within %ds — ensure messenger / messenger-notify workers are running (make up / make restart).',
                $eventId ?? '(none)',
                max(0, $waitSeconds),
            );
        }

        if (!$vapidConfigured) {
            $warnings[] = 'Web Push VAPID keys are not configured — browser push cannot be sent.';
        }
        if (0 === $pushCount) {
            $warnings[] = 'No push_subscription rows yet — HTTP 200 only means ingest ACK; enable alerts in the UI and allow the browser prompt.';
        }

        $notes = array_merge($notes, $this->pushReadinessNotes($vapidConfigured, $pushCount));

        return new BeaconDogfoodDiagnosticReport(
            projectFound: true,
            projectName: $project->getName(),
            vapidConfigured: $vapidConfigured,
            pushSubscriptionCount: $pushCount,
            priorIssueExisted: $priorIssueExisted,
            priorEventCount: $priorEventCount,
            eventPersisted: $eventPersisted,
            issueUuid: $issueUuid,
            issueEventCount: $issueEventCount,
            warnings: $warnings,
            notes: $notes,
        );
    }

    /**
     * @return list<string>
     */
    private function pushReadinessNotes(bool $vapidConfigured, int $pushCount): array
    {
        return [
            'VAPID configured: '.($vapidConfigured ? 'yes' : 'no'),
            'Push subscriptions: '.$pushCount,
        ];
    }

    private function waitForEvent(string $eventId, int $waitSeconds): ?Event
    {
        if ($waitSeconds <= 0) {
            $this->entityManager->clear();

            return $this->eventRepository->findOneByEventId($eventId);
        }

        $deadline = microtime(true) + $waitSeconds;
        do {
            $this->entityManager->clear();
            $event = $this->eventRepository->findOneByEventId($eventId);
            if ($event instanceof Event) {
                return $event;
            }
            usleep(200_000);
        } while (microtime(true) < $deadline);

        return null;
    }
}
