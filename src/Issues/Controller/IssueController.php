<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Entity\IssueSavedView;
use App\Issues\Form\IssueAssigneeType;
use App\Issues\IssueListSort;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueCommentRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Issues\Service\IssueHistoryRecorder;
use App\Issues\Service\IssueMergeService;
use App\Notifications\Service\NotificationDispatcher;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\IssuePriority;
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
    /** @var list<string> */
    private const array SAVED_VIEW_QUERY_KEYS = [
        'q', 'level', 'status', 'environment', 'release', 'compare', 'assignee', 'priority',
        'tag', 'url', 'user', 'sort', 'dir', 'per_page',
    ];

    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EventRepository $eventRepository,
        private readonly IssueHistoryEntryRepository $historyEntryRepository,
        private readonly IssueCommentRepository $commentRepository,
        private readonly IssueSavedViewRepository $savedViewRepository,
        private readonly IssueHistoryRecorder $historyRecorder,
        private readonly IssueMergeService $issueMergeService,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly NotificationDispatcher $notificationDispatcher,
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
        $release = $request->query->getString('release') ?: null;
        $compare = $request->query->getString('compare') ?: null;
        $tag = $request->query->getString('tag') ?: null;
        $url = $request->query->getString('url') ?: null;
        $userFilter = $request->query->getString('user') ?: null;
        $priorityParam = $request->query->getString('priority');
        $priority = '' !== $priorityParam ? IssuePriority::tryFrom($priorityParam) : null;

        $total = $this->issueRepository->countSearch(
            $project,
            $q,
            $level,
            $status,
            $environment,
            $release,
            $priority,
            $assignee,
            $unassignedOnly,
            tag: $tag,
            url: $url,
            user: $userFilter,
        );
        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $issues = $this->issueRepository->search(
            $project,
            $q,
            $level,
            $status,
            $environment,
            $release,
            $priority,
            $assignee,
            $unassignedOnly,
            $sort,
            $perPage,
            $offset,
            tag: $tag,
            url: $url,
            user: $userFilter,
        );
        $occurrenceByIssue = $this->eventRepository->occurrenceStatsForIssues($issues);

        $compareResult = null;
        if (null !== $compare && null !== $environment) {
            $compareResult = $this->buildEnvironmentCompare($project, $environment, $compare);
        }

        $savedViews = $this->savedViewRepository->findForUserAndProject($user, $project);

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'occurrenceByIssue' => $occurrenceByIssue,
            'members' => $members,
            'sort' => $sort,
            'compareResult' => $compareResult,
            'savedViews' => $savedViews,
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
                'release' => $request->query->getString('release'),
                'compare' => $request->query->getString('compare'),
                'tag' => $request->query->getString('tag'),
                'url' => $request->query->getString('url'),
                'user' => $request->query->getString('user'),
                'priority' => $priority instanceof IssuePriority ? $priority->value : '',
                'assignee' => $assigneeFilter,
                'sort' => $sort->field,
                'dir' => $sort->direction,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    /**
     * @return array{environmentA: string, environmentB: string, onlyA: list<Issue>, onlyB: list<Issue>, both: list<Issue>}
     */
    private function buildEnvironmentCompare(Project $project, string $environmentA, string $environmentB): array
    {
        $setA = $this->issueRepository->findByLastEnvironment($project, $environmentA);
        $setB = $this->issueRepository->findByLastEnvironment($project, $environmentB);

        $byIdA = [];
        foreach ($setA as $issue) {
            $id = $issue->getId();
            if (null !== $id) {
                $byIdA[$id] = $issue;
            }
        }
        $byIdB = [];
        foreach ($setB as $issue) {
            $id = $issue->getId();
            if (null !== $id) {
                $byIdB[$id] = $issue;
            }
        }

        $onlyA = [];
        $both = [];
        foreach ($byIdA as $id => $issue) {
            if (isset($byIdB[$id])) {
                $both[] = $issue;
            } else {
                $onlyA[] = $issue;
            }
        }
        $onlyB = [];
        foreach ($byIdB as $id => $issue) {
            if (!isset($byIdA[$id])) {
                $onlyB[] = $issue;
            }
        }

        return [
            'environmentA' => $environmentA,
            'environmentB' => $environmentB,
            'onlyA' => \array_slice($onlyA, 0, 50),
            'onlyB' => \array_slice($onlyB, 0, 50),
            'both' => \array_slice($both, 0, 50),
        ];
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
        $comments = $this->commentRepository->findLatestForIssue($issue);
        $duplicateCandidates = $this->issueRepository->findDuplicateCandidates($project, $issue);

        return $this->render('issue/show.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'events' => $events,
            'latestEvent' => $latestEvent,
            'occurrence' => $occurrence,
            'assigneeForm' => $assigneeForm->createView(),
            'issueHistory' => $history,
            'comments' => $comments,
            'duplicateCandidates' => $duplicateCandidates,
            'priorities' => IssuePriority::cases(),
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
                    $this->notificationDispatcher->dispatchIssueAssigned(
                        $project,
                        $issue,
                        $previousAssignee,
                        $assignee,
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
            if (IssueStatus::Resolved === $next) {
                $this->notificationDispatcher->dispatchIssueResolved($project, $issue);
            } elseif (
                IssueStatus::Unresolved === $next
                && \in_array($previous, [IssueStatus::Resolved, IssueStatus::Ignored], true)
            ) {
                $this->notificationDispatcher->dispatchIssueReopened($project, $issue);
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'issues.status_saved');
        }

        return $this->redirectToRoute('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }

    #[Route('/projects/{projectId}/issues/{id}/priority', name: 'issue_priority', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function priority(
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

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];

        if (!$this->isCsrfTokenValid('issue_priority', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.priority_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $next = IssuePriority::tryFrom($request->request->getString('priority'));
        if (!$next instanceof IssuePriority) {
            $this->addFlash('error', 'issues.priority_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $previous = $issue->getPriority();
        if ($previous !== $next) {
            $issue->setPriority($next);
            $this->userActionRecorder->record(
                UserActionType::IssuePriorityChanged,
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
            $this->addFlash('success', 'issues.priority_saved');
        }

        return $this->redirectToRoute('issue_show', $showParams);
    }

    #[Route('/projects/{projectId}/issues/{id}/comments', name: 'issue_comment_add', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function addComment(
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

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];

        if (!$this->isCsrfTokenValid('issue_comment', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.comment_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $body = trim($request->request->getString('body'));
        if ('' === $body) {
            $this->addFlash('error', 'issues.comment_empty');

            return $this->redirectToRoute('issue_show', $showParams);
        }
        if (mb_strlen($body) > IssueComment::BODY_MAX_LENGTH) {
            $this->addFlash('error', 'issues.comment_too_long');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $comment = new IssueComment();
        $comment->setIssue($issue);
        $comment->setAuthor($user);
        $comment->setBody($body);
        $this->entityManager->persist($comment);
        $issue->addComment($comment);

        $this->userActionRecorder->record(
            UserActionType::IssueCommented,
            $user,
            $user,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'comment_uuid' => $comment->getUuid(),
            ],
        );
        $this->notificationDispatcher->dispatchIssueCommented($project, $issue, $comment);
        $this->entityManager->flush();
        $this->addFlash('success', 'issues.comment_saved');

        return $this->redirectToRoute('issue_show', $showParams);
    }

    #[Route('/projects/{projectId}/issues/{id}/duplicate', name: 'issue_mark_duplicate', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    public function markDuplicate(
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

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];

        if (!$this->isCsrfTokenValid('issue_duplicate', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.duplicate_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $canonicalUuid = trim($request->request->getString('canonical_uuid'));
        if ('' === $canonicalUuid) {
            $this->addFlash('error', 'issues.duplicate_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        if ($canonicalUuid === $issue->getUuid()) {
            $this->addFlash('error', 'issues.duplicate_self');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $canonical = $this->issueRepository->findOneByProjectAndUuid($project, $canonicalUuid);
        if (!$canonical instanceof Issue) {
            $this->addFlash('error', 'issues.duplicate_not_found');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        if ($canonical->getDuplicateOf()?->getId() === $issue->getId()) {
            $this->addFlash('error', 'issues.duplicate_circular');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $mergeEvents = $request->request->getBoolean('merge_events');
        if ($mergeEvents) {
            try {
                $moved = $this->issueMergeService->mergeIntoCanonical($issue, $canonical, $user);
            } catch (\InvalidArgumentException) {
                $this->addFlash('error', 'issues.merge_failed');

                return $this->redirectToRoute('issue_show', $showParams);
            }
            $this->userActionRecorder->record(
                UserActionType::IssueMerged,
                $user,
                $user,
                [
                    'project_uuid' => $project->getUuid(),
                    'project_name' => $project->getName(),
                    'issue_uuid' => $issue->getUuid(),
                    'issue_title' => $issue->getTitle(),
                    'canonical_uuid' => $canonical->getUuid(),
                    'canonical_title' => $canonical->getTitle(),
                    'events_moved' => $moved,
                ],
            );
            $this->notificationDispatcher->dispatchIssueDuplicated($project, $issue, $canonical);
            $this->addFlash('success', 'issues.merge_saved');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $canonical->getUuid(),
            ]);
        }

        $previousStatus = $issue->getStatus();
        $issue->setDuplicateOf($canonical);
        $issue->setStatus(IssueStatus::Ignored);
        if (IssueStatus::Ignored !== $previousStatus) {
            $this->historyRecorder->recordStatusChange($issue, $previousStatus, IssueStatus::Ignored, $user);
        }

        $this->userActionRecorder->record(
            UserActionType::IssueMarkedDuplicate,
            $user,
            $user,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
                'issue_uuid' => $issue->getUuid(),
                'issue_title' => $issue->getTitle(),
                'canonical_uuid' => $canonical->getUuid(),
                'canonical_title' => $canonical->getTitle(),
            ],
        );
        $this->notificationDispatcher->dispatchIssueDuplicated($project, $issue, $canonical);
        $this->entityManager->flush();
        $this->addFlash('success', 'issues.duplicate_saved');

        return $this->redirectToRoute('issue_show', $showParams);
    }

    #[Route('/projects/{id}/issues/views', name: 'issue_view_save', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function saveView(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        if (!$this->isCsrfTokenValid('issue_view_save', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.view_invalid');

            return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()]);
        }

        $name = trim($request->request->getString('name'));
        if ('' === $name) {
            $this->addFlash('error', 'issues.view_name_empty');

            return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()] + $this->filterQueryFromRequest($request));
        }

        $queryJson = $this->filterQueryFromRequest($request);
        $view = new IssueSavedView();
        $view->setUser($user);
        $view->setProject($project);
        $view->setName($name);
        $view->setQueryJson($queryJson);
        $this->entityManager->persist($view);
        $this->entityManager->flush();
        $this->addFlash('success', 'issues.view_saved');

        return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()] + $queryJson);
    }

    #[Route('/projects/{id}/issues/views/{viewUuid}', name: 'issue_view_apply', requirements: ['id' => Requirement::UUID, 'viewUuid' => Requirement::UUID], methods: ['GET'])]
    public function applyView(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        string $viewUuid,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $view = $this->savedViewRepository->findOneForUserAndProject($viewUuid, $user, $project);
        if (!$view instanceof IssueSavedView) {
            throw $this->createNotFoundException();
        }

        $query = [];
        foreach ($view->getQueryJson() as $key => $value) {
            if (!\is_string($key) || !\in_array($key, self::SAVED_VIEW_QUERY_KEYS, true)) {
                continue;
            }
            if (null === $value || '' === $value) {
                continue;
            }
            if (!\is_scalar($value)) {
                continue;
            }
            $query[$key] = (string) $value;
        }

        return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()] + $query);
    }

    #[Route('/projects/{id}/issues/views/{viewUuid}/delete', name: 'issue_view_delete', requirements: ['id' => Requirement::UUID, 'viewUuid' => Requirement::UUID], methods: ['POST'])]
    public function deleteView(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        string $viewUuid,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        if (!$this->isCsrfTokenValid('issue_view_delete', $request->request->getString('_token'))) {
            $this->addFlash('error', 'issues.view_invalid');

            return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()]);
        }

        $view = $this->savedViewRepository->findOneForUserAndProject($viewUuid, $user, $project);
        if ($view instanceof IssueSavedView) {
            $this->entityManager->remove($view);
            $this->entityManager->flush();
            $this->addFlash('success', 'issues.view_deleted');
        }

        return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()]);
    }

    /**
     * @return array<string, string|int>
     */
    private function filterQueryFromRequest(Request $request): array
    {
        $query = [];
        foreach (self::SAVED_VIEW_QUERY_KEYS as $key) {
            if ($request->request->has($key)) {
                $value = $request->request->get($key);
            } elseif ($request->query->has($key)) {
                $value = $request->query->get($key);
            } else {
                continue;
            }
            if (null === $value || '' === $value) {
                continue;
            }
            if ('per_page' === $key) {
                $query[$key] = (int) $value;
                continue;
            }
            $query[$key] = \is_int($value) || (\is_string($value) && ctype_digit($value))
                ? (int) $value
                : (string) $value;
        }

        return $query;
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
