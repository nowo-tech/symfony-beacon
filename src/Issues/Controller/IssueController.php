<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Dto\IssueOccurrenceStats;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Form\IssueAssigneeType;
use App\Issues\IssueListSort;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\IssueStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project issues list/detail, assignee updates, and status changes.
 */
#[IsGranted('ROLE_USER')]
final class IssueController extends AbstractController
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EventRepository $eventRepository,
        private readonly IssueHistoryEntryRepository $historyEntryRepository,
        private readonly IssueHistoryRecorder $historyRecorder,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/{id}/issues', name: 'issue_index', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::ProjectOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

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

        $sort = IssueListSort::fromQuery(
            $request->query->getString('sort') ?: null,
            $request->query->getString('dir') ?: null,
        );

        $perPage = $request->query->getInt('per_page', 25);
        if (!\in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 25;
        }
        $page = max(1, $request->query->getInt('page', 1));

        $q = $request->query->getString('q') ?: null;
        $level = $request->query->getString('level') ?: null;
        $environment = $request->query->getString('environment') ?: null;

        $total = $this->issueRepository->countSearch(
            $project,
            $q,
            $level,
            $status,
            $environment,
            $assignee,
            $unassignedOnly,
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        if ($sort->isOccurrenceSortable()) {
            $allIssues = $this->issueRepository->search(
                $project,
                $q,
                $level,
                $status,
                $environment,
                $assignee,
                $unassignedOnly,
                $sort,
            );
            $occurrenceByIssue = $this->eventRepository->occurrenceStatsForIssues($allIssues);
            $allIssues = $this->sortIssuesByOccurrence($allIssues, $occurrenceByIssue, $sort);
            $issues = array_values(\array_slice($allIssues, $offset, $perPage));
            $pageIds = [];
            foreach ($issues as $issue) {
                $id = $issue->getId();
                if (null !== $id) {
                    $pageIds[$id] = true;
                }
            }
            $occurrenceByIssue = array_intersect_key($occurrenceByIssue, $pageIds);
        } else {
            $issues = $this->issueRepository->search(
                $project,
                $q,
                $level,
                $status,
                $environment,
                $assignee,
                $unassignedOnly,
                $sort,
                $perPage,
                $offset,
            );
            $occurrenceByIssue = $this->eventRepository->occurrenceStatsForIssues($issues);
        }

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'occurrenceByIssue' => $occurrenceByIssue,
            'members' => $members,
            'sort' => $sort,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
            ],
            'filters' => [
                'q' => $request->query->getString('q'),
                'level' => $request->query->getString('level'),
                'status' => $status->value,
                'environment' => $request->query->getString('environment'),
                'assignee' => $assigneeFilter,
                'sort' => $sort->field,
                'dir' => $sort->direction,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * @param list<Issue>                      $issues
     * @param array<int, IssueOccurrenceStats> $occurrenceByIssue
     *
     * @return list<Issue>
     */
    private function sortIssuesByOccurrence(array $issues, array $occurrenceByIssue, IssueListSort $sort): array
    {
        usort($issues, static function (Issue $a, Issue $b) use ($occurrenceByIssue, $sort): int {
            $statsA = $occurrenceByIssue[$a->getId() ?? 0] ?? null;
            $statsB = $occurrenceByIssue[$b->getId() ?? 0] ?? null;
            $valueA = match ($sort->field) {
                'events_24h' => $statsA instanceof IssueOccurrenceStats ? $statsA->last24h : 0,
                'events_7d' => $statsA instanceof IssueOccurrenceStats ? $statsA->last7d : 0,
                'events_30d' => $statsA instanceof IssueOccurrenceStats ? $statsA->last30d : 0,
                default => 0,
            };
            $valueB = match ($sort->field) {
                'events_24h' => $statsB instanceof IssueOccurrenceStats ? $statsB->last24h : 0,
                'events_7d' => $statsB instanceof IssueOccurrenceStats ? $statsB->last7d : 0,
                'events_30d' => $statsB instanceof IssueOccurrenceStats ? $statsB->last30d : 0,
                default => 0,
            };

            $cmp = $valueA <=> $valueB;
            if (0 === $cmp) {
                $cmp = $b->getLastSeen() <=> $a->getLastSeen();
            }

            return 'asc' === $sort->direction ? $cmp : -$cmp;
        });

        return $issues;
    }

    #[Route('/projects/{projectId}/issues/{id}', name: 'issue_show', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::IssueOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'issue_uuid' => $issue->getUuid(),
            'issue_title' => $issue->getTitle(),
        ]);

        $events = $this->eventRepository->findLatestForIssue($issue);
        $latestEvent = $events[0] ?? null;
        $occurrence = $this->eventRepository->occurrenceStatsForIssue($issue);
        $assigneeForm = $this->createForm(IssueAssigneeType::class, $issue, [
            'project_id' => $project->getId(),
            'action' => $this->generateUrl('issue_assign', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
            'method' => 'POST',
        ]);
        $history = $this->historyEntryRepository->findLatestForIssue($issue);

        return $this->render('issue/show.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'events' => $events,
            'latestEvent' => $latestEvent,
            'occurrence' => $occurrence,
            'assigneeForm' => $assigneeForm->createView(),
            'issueHistory' => $history,
        ]);
    }

    #[Route('/projects/{projectId}/issues/{id}/assign', name: 'issue_assign', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function assign(
        Request $request,
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        $previousAssignee = $issue->getAssignee();

        $form = $this->createForm(IssueAssigneeType::class, $issue, [
            'project_id' => $project->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignee = $issue->getAssignee();
            if ($assignee instanceof User && null === $this->projectAccess->resolveAccess($project, $assignee)) {
                $this->addFlash('error', 'issues.assignee_not_member');
                $issue->setAssignee($previousAssignee);
            } else {
                $this->historyRecorder->recordAssigneeChange($issue, $previousAssignee, $assignee, $user);
                if ($previousAssignee?->getId() !== $assignee?->getId()) {
                    $this->userActionRecorder->record(
                        UserActionType::IssueAssigned,
                        $user,
                        $assignee ?? $user,
                        [
                            'project_uuid' => $project->getUuid(),
                            'project_name' => $project->getName(),
                            'issue_uuid' => $issue->getUuid(),
                            'issue_title' => $issue->getTitle(),
                            'from' => $previousAssignee?->getDisplayName(),
                            'to' => $assignee?->getDisplayName(),
                        ],
                    );
                }
                $this->entityManager->flush();
                $this->addFlash('success', 'issues.assignee_saved');
            }

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        $this->addFlash('error', 'issues.assignee_invalid');

        return $this->redirectToRoute('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }

    #[Route('/projects/{projectId}/issues/{id}/status', name: 'issue_status', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function status(
        Request $request,
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        if (!$this->isCsrfTokenValid('issue_status', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.status_invalid');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        $next = IssueStatus::tryFrom($request->request->getString('status'));
        if (!$next instanceof IssueStatus) {
            $this->addFlash('error', 'issues.status_invalid');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        $previous = $issue->getStatus();
        if ($previous !== $next) {
            $issue->setStatus($next);
            $this->historyRecorder->recordStatusChange($issue, $previous, $next, $user);
            $this->userActionRecorder->record(
                UserActionType::IssueStatusChanged,
                $user,
                $user,
                [
                    'project_uuid' => $project->getUuid(),
                    'project_name' => $project->getName(),
                    'issue_uuid' => $issue->getUuid(),
                    'issue_title' => $issue->getTitle(),
                    'from' => $previous->value,
                    'to' => $next->value,
                ],
            );
            $this->entityManager->flush();
            $this->addFlash('success', 'issues.status_saved');
        }

        return $this->redirectToRoute('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }

    #[Route('/projects/{projectId}/events/{eventId}', name: 'event_show', requirements: ['projectId' => Requirement::UUID], methods: ['GET'])]
    public function eventShow(string $projectId, string $eventId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $event = $this->eventRepository->findOneByEventId($eventId);
        $project = $event?->getIssue()?->getProject();
        if (!$event instanceof Event || !$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        $issue = $event->getIssue();
        $this->userActionRecorder->recordAndFlush(UserActionType::EventOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'issue_uuid' => $issue?->getUuid(),
            'issue_title' => $issue?->getTitle(),
            'event_id' => $event->getEventId(),
        ]);

        return $this->render('issue/event.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'event' => $event,
        ]);
    }
}
