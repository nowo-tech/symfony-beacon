<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Form\IssueAssigneeType;
use App\Issues\Form\IssueCommentType;
use App\Issues\Form\IssueDuplicateType;
use App\Issues\Form\IssuePriorityType;
use App\Issues\Form\IssueStatusType;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueCommentRepository;
use App\Issues\Repository\IssueHistoryEntryRepository;
use App\Issues\Repository\IssueRepository;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueDuplicateMarker;
use App\Issues\Service\IssueStatusChanger;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Issue detail, triage mutations, and event show.
 */
#[IsGranted('ROLE_USER')]
final class IssueDetailController extends AbstractController
{
    public function __construct(
        private readonly IssueCommentRepository $commentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly IssueHistoryEntryRepository $historyEntryRepository,
        private readonly IssueAssigneeChanger $issueAssigneeChanger,
        private readonly IssueCommentCreator $issueCommentCreator,
        private readonly IssueDuplicateMarker $issueDuplicateMarker,
        private readonly IssueRepository $issueRepository,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly ProjectAccessService $projectAccess,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{projectId}/issues/{id}', name: 'issue_show', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET'])]
    public function show(string $projectId, string $id): Response
    {
        $issue = $this->issueRepository->findOneByUuidHydrated($id);
        if (!$issue instanceof Issue) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $access = $this->projectAccess->requireIssueRead($project, $user, $issue->getUuid());

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
        $similarIssues = $this->issueRepository->findSimilarIssues($issue);
        $statusForms = [];
        foreach (IssueStatus::cases() as $status) {
            if ($issue->getStatus() === $status) {
                continue;
            }

            $statusForms[$status->value] = $this->createForm(IssueStatusType::class, [
                'status' => $status->value,
            ], [
                'action' => $this->generateUrl('issue_status', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_status',
            ])->createView();
        }
        $priorityForm = $this->createForm(IssuePriorityType::class, [
            'priority' => $issue->getPriority()->value,
        ], [
            'action' => $this->generateUrl('issue_priority', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
            'method' => 'POST',
            'csrf_token_id' => 'issue_priority',
        ]);
        $quickDuplicateForms = [];
        foreach ($similarIssues as $similarIssue) {
            $quickDuplicateForms[$similarIssue->getId()] = $this->createForm(IssueDuplicateType::class, [
                'canonical_uuid' => $similarIssue->getUuid(),
            ], [
                'action' => $this->generateUrl('issue_mark_duplicate', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_duplicate',
            ])->createView();
        }
        $duplicateDialogForm = $this->createForm(IssueDuplicateType::class, null, [
            'action' => $this->generateUrl('issue_mark_duplicate', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
            'method' => 'POST',
            'csrf_token_id' => 'issue_duplicate',
        ]);

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
            'similarIssues' => $similarIssues,
            'statusForms' => $statusForms,
            'priorityForm' => $priorityForm->createView(),
            'quickDuplicateForms' => $quickDuplicateForms,
            'duplicateDialogForm' => $duplicateDialogForm->createView(),
            'commentForm' => $this->createForm(IssueCommentType::class, [
                'body' => '',
            ], [
                'action' => $this->generateUrl('issue_comment_add', ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_comment',
            ])->createView(),
            'can_triage' => $access->canTriageIssues(),
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
        $this->projectAccess->requireTriage($project, $user);

        $previousAssignee = $issue->getAssignee();

        $form = $this->createForm(IssueAssigneeType::class, $issue, [
            'project_id' => $project->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $assignee = $issue->getAssignee();
            // Form already mutated the entity; restore previous so the changer can detect a real change.
            $issue->setAssignee($previousAssignee);
            try {
                if ($this->issueAssigneeChanger->assign($issue, $assignee, $user)) {
                    $this->addFlash('success', 'issues.assignee_saved');
                }
            } catch (InvalidArgumentException) {
                $this->addFlash('error', 'issues.assignee_not_member');

                return $this->redirectToRoute('issue_show', [
                    'projectId' => $project->getUuid(),
                    'id' => $issue->getUuid(),
                ]);
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
        $this->projectAccess->requireTriage($project, $user);
        $form = $this->createForm(IssueStatusType::class, null, [
            'csrf_token_id' => 'issue_status',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'issues.status_invalid');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        $data = $form->getData();
        $next = IssueStatus::tryFrom((string) ((\is_array($data) ? $data['status'] : null) ?? ''));
        if (!$next instanceof IssueStatus) {
            $this->addFlash('error', 'issues.status_invalid');

            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        if ($this->issueStatusChanger->change($issue, $next, $user)) {
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
        $this->projectAccess->requireTriage($project, $user);

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];
        $form = $this->createForm(IssuePriorityType::class, null, [
            'csrf_token_id' => 'issue_priority',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'issues.priority_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $data = $form->getData();
        $next = IssuePriority::tryFrom((string) ((\is_array($data) ? $data['priority'] : null) ?? ''));
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
        $this->projectAccess->requireTriage($project, $user);

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];

        $form = $this->createForm(IssueCommentType::class, null, [
            'csrf_token_id' => 'issue_comment',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'issues.comment_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        /** @var array{body?: string|null} $data */
        $data = $form->getData();
        $body = trim((string) ($data['body'] ?? ''));
        try {
            $this->issueCommentCreator->create($issue, $user, $body);
            $this->addFlash('success', 'issues.comment_saved');
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', match ($e->getMessage()) {
                'empty' => 'issues.comment_empty',
                'too_long' => 'issues.comment_too_long',
                default => 'issues.comment_invalid',
            });
        }

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
        $this->projectAccess->requireTriage($project, $user);

        $showParams = ['projectId' => $project->getUuid(), 'id' => $issue->getUuid()];
        $form = $this->createForm(IssueDuplicateType::class, null, [
            'csrf_token_id' => 'issue_duplicate',
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'issues.duplicate_invalid');

            return $this->redirectToRoute('issue_show', $showParams);
        }

        $data = $form->getData();
        $canonicalUuid = trim((string) ((\is_array($data) ? $data['canonical_uuid'] : null) ?? ''));
        $mergeEvents = (bool) ((\is_array($data) ? $data['merge_events'] : null) ?? false);
        $result = $this->issueDuplicateMarker->mark($project, $issue, $user, $canonicalUuid, $mergeEvents);

        $this->addFlash($result['ok'] ? 'success' : 'error', $result['flash']);

        if ($result['ok'] && null !== $result['redirect_issue_uuid']) {
            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $result['redirect_issue_uuid'],
            ]);
        }

        return $this->redirectToRoute('issue_show', $showParams);
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
        $issue = $event->getIssue();
        if (!$issue instanceof Issue) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireIssueRead($project, $user, $issue->getUuid());

        $this->userActionRecorder->recordAndFlush(UserActionType::EventOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'issue_uuid' => $issue->getUuid(),
            'issue_title' => $issue->getTitle(),
            'event_id' => $event->getEventId(),
        ]);

        return $this->render('issue/event.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'event' => $event,
        ]);
    }
}
