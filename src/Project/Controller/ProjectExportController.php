<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\IssuePriority;
use App\Shared\IssueStatus;
use App\Shared\ProjectRole;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Owner/admin CSV and JSON export of project issues and events.
 */
#[IsGranted('ROLE_USER')]
final class ProjectExportController extends AbstractController
{
    public const int EXPORT_LIMIT = 1000;

    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EventRepository $eventRepository,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route(
        '/projects/{uuid}/export/issues.{_format}',
        name: 'project_export_issues',
        requirements: ['uuid' => Requirement::UUID, '_format' => 'csv|json'],
        methods: ['GET'],
    )]
    public function exportIssues(
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Project $project,
        Request $request,
        string $_format,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $issues = $this->resolveIssues($project, $request);

        if ('json' === $_format) {
            return new JsonResponse([
                'project' => [
                    'uuid' => $project->getUuid(),
                    'slug' => $project->getSlug(),
                ],
                'limit' => self::EXPORT_LIMIT,
                'count' => \count($issues),
                'issues' => array_map($this->issueToArray(...), $issues),
            ]);
        }

        return $this->csvStream(
            'issues-'.$project->getSlug().'.csv',
            [
                'uuid',
                'title',
                'level',
                'status',
                'priority',
                'culprit',
                'event_count',
                'first_seen',
                'last_seen',
                'first_release',
                'last_release',
                'last_environment',
                'assignee_email',
                'duplicate_of_uuid',
            ],
            static function () use ($issues): iterable {
                foreach ($issues as $issue) {
                    yield [
                        $issue->getUuid(),
                        $issue->getTitle(),
                        $issue->getLevel(),
                        $issue->getStatus()->value,
                        $issue->getPriority()->value,
                        $issue->getCulprit(),
                        (string) $issue->getEventCount(),
                        $issue->getFirstSeen()->format(\DATE_ATOM),
                        $issue->getLastSeen()->format(\DATE_ATOM),
                        $issue->getFirstRelease() ?? '',
                        $issue->getLastRelease() ?? '',
                        $issue->getLastEnvironment() ?? '',
                        $issue->getAssignee()?->getEmail() ?? '',
                        $issue->getDuplicateOf()?->getUuid() ?? '',
                    ];
                }
            },
        );
    }

    #[Route(
        '/projects/{uuid}/export/events.{_format}',
        name: 'project_export_events',
        requirements: ['uuid' => Requirement::UUID, '_format' => 'csv|json'],
        methods: ['GET'],
    )]
    public function exportEvents(
        #[MapEntity(mapping: ['uuid' => 'uuid'])]
        Project $project,
        Request $request,
        string $_format,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $statusParam = $request->query->getString('status');
        $status = '' !== $statusParam ? IssueStatus::tryFrom($statusParam) : null;

        $events = $this->eventRepository->searchForExport(
            $project,
            $request->query->getString('q') ?: null,
            $request->query->getString('level') ?: null,
            $status,
            $request->query->getString('environment') ?: null,
            $request->query->getString('release') ?: null,
            self::EXPORT_LIMIT,
        );

        if ('json' === $_format) {
            return new JsonResponse([
                'project' => [
                    'uuid' => $project->getUuid(),
                    'slug' => $project->getSlug(),
                ],
                'limit' => self::EXPORT_LIMIT,
                'count' => \count($events),
                'events' => array_map($this->eventToArray(...), $events),
            ]);
        }

        return $this->csvStream(
            'events-'.$project->getSlug().'.csv',
            [
                'event_id',
                'issue_uuid',
                'issue_title',
                'issue_level',
                'issue_status',
                'environment',
                'release',
                'platform',
                'received_at',
                'event_timestamp',
            ],
            static function () use ($events): iterable {
                foreach ($events as $event) {
                    $issue = $event->getIssue();
                    yield [
                        $event->getEventId(),
                        $issue?->getUuid() ?? '',
                        $issue?->getTitle() ?? '',
                        $issue?->getLevel() ?? '',
                        $issue?->getStatus()->value ?? '',
                        $event->getEnvironment() ?? '',
                        $event->getReleaseVersion() ?? '',
                        $event->getPlatform(),
                        $event->getReceivedAt()->format(\DATE_ATOM),
                        $event->getEventTimestamp()->format(\DATE_ATOM),
                    ];
                }
            },
        );
    }

    /**
     * @return list<Issue>
     */
    private function resolveIssues(Project $project, Request $request): array
    {
        $statusParam = $request->query->getString('status');
        $status = '' !== $statusParam
            ? (IssueStatus::tryFrom($statusParam) ?? IssueStatus::Unresolved)
            : IssueStatus::Unresolved;

        $members = $this->membershipRepository->findUsersByProject($project);
        $assigneeFilter = $request->query->getString('assignee');
        $assignee = null;
        $unassignedOnly = 'unassigned' === $assigneeFilter;
        if (!$unassignedOnly && '' !== $assigneeFilter && ctype_digit($assigneeFilter)) {
            foreach ($members as $member) {
                if ($member->getId() === (int) $assigneeFilter) {
                    $assignee = $member;
                    break;
                }
            }
        }

        $priorityParam = $request->query->getString('priority');
        $priority = '' !== $priorityParam ? IssuePriority::tryFrom($priorityParam) : null;

        return $this->issueRepository->search(
            $project,
            $request->query->getString('q') ?: null,
            $request->query->getString('level') ?: null,
            $status,
            $request->query->getString('environment') ?: null,
            $request->query->getString('release') ?: null,
            $priority,
            $assignee,
            $unassignedOnly,
            null,
            self::EXPORT_LIMIT,
            0,
            tag: $request->query->getString('tag') ?: null,
            url: $request->query->getString('url') ?: null,
            user: $request->query->getString('user') ?: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function issueToArray(Issue $issue): array
    {
        return [
            'uuid' => $issue->getUuid(),
            'title' => $issue->getTitle(),
            'level' => $issue->getLevel(),
            'status' => $issue->getStatus()->value,
            'priority' => $issue->getPriority()->value,
            'culprit' => $issue->getCulprit(),
            'event_count' => $issue->getEventCount(),
            'first_seen' => $issue->getFirstSeen()->format(\DATE_ATOM),
            'last_seen' => $issue->getLastSeen()->format(\DATE_ATOM),
            'first_release' => $issue->getFirstRelease(),
            'last_release' => $issue->getLastRelease(),
            'last_environment' => $issue->getLastEnvironment(),
            'assignee_email' => $issue->getAssignee()?->getEmail(),
            'duplicate_of_uuid' => $issue->getDuplicateOf()?->getUuid(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventToArray(Event $event): array
    {
        $issue = $event->getIssue();

        return [
            'event_id' => $event->getEventId(),
            'issue_uuid' => $issue?->getUuid(),
            'issue_title' => $issue?->getTitle(),
            'issue_level' => $issue?->getLevel(),
            'issue_status' => $issue?->getStatus()->value,
            'environment' => $event->getEnvironment(),
            'release' => $event->getReleaseVersion(),
            'platform' => $event->getPlatform(),
            'received_at' => $event->getReceivedAt()->format(\DATE_ATOM),
            'event_timestamp' => $event->getEventTimestamp()->format(\DATE_ATOM),
        ];
    }

    /**
     * @param list<string>                       $headers
     * @param callable(): iterable<list<string>> $rows
     */
    private function csvStream(string $filename, array $headers, callable $rows): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            if (false === $out) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ',', '"', '\\');
            foreach ($rows() as $row) {
                fputcsv($out, $row, ',', '"', '\\');
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
