<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueShowTab;
use App\Issues\Enum\IssueStatus;
use App\Issues\Form\IssueAssigneeType;
use App\Issues\Form\IssueCommentType;
use App\Issues\Form\IssueDuplicateType;
use App\Issues\Form\IssuePriorityType;
use App\Issues\Form\IssueStatusType;
use App\Issues\Service\IssueAssigneeChanger;
use App\Issues\Service\IssueCommentCreator;
use App\Issues\Service\IssueDuplicateMarker;
use App\Issues\Service\IssueShowPageBuilder;
use App\Issues\Service\IssueStatusChanger;
use App\Project\Entity\Project;
use App\Project\Security\ProjectPermission;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly IssueAssigneeChanger $issueAssigneeChanger,
        private readonly IssueCommentCreator $issueCommentCreator,
        private readonly IssueDuplicateMarker $issueDuplicateMarker,
        private readonly IssueShowPageBuilder $issueShowPageBuilder,
        private readonly IssueStatusChanger $issueStatusChanger,
        private readonly ProjectAccessService $projectAccess,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route(
        '/projects/{projectId}/issues/{id}/{tab}',
        name: 'issue_show',
        requirements: [
            'projectId' => Requirement::UUID,
            'id' => Requirement::UUID,
            'tab' => 'main|similar|history',
        ],
        defaults: ['tab' => 'main'],
        methods: ['GET'],
    )]
    public function show(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
        string $tab = 'main',
    ): Response {
        $tabEnum = IssueShowTab::tryFrom($tab);
        if (null === $tabEnum) {
            throw $this->createNotFoundException();
        }

        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
        $access = $this->projectAccess->requireIssueRead($project, $user, $issue->getUuid());

        $this->userActionRecorder->recordAndFlush(UserActionType::IssueOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'issue_uuid' => $issue->getUuid(),
            'issue_title' => $issue->getTitle(),
        ]);

        return $this->render(
            'issue/show.html.twig',
            $this->issueShowPageBuilder->build($project, $issue, $user, $access, $tabEnum),
        );
    }

    #[Route('/projects/{projectId}/issues/{id}/assign', name: 'issue_assign', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
    public function assign(
        Request $request,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
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
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
    public function status(
        Request $request,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
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

        try {
            if ($this->issueStatusChanger->change($issue, $next, $user)) {
                $this->addFlash('success', 'issues.status_saved');
            }
        } catch (InvalidArgumentException) {
            $this->addFlash('error', 'issues.status_invalid');
        }

        return $this->redirectToRoute('issue_show', [
            'projectId' => $project->getUuid(),
            'id' => $issue->getUuid(),
        ]);
    }

    #[Route('/projects/{projectId}/issues/{id}/priority', name: 'issue_priority', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
    public function priority(
        Request $request,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
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
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
    public function addComment(
        Request $request,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
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
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
    public function markDuplicate(
        Request $request,
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Issue $issue,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        if ($issue->getProject()?->getId() !== $project->getId()) {
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
    public function eventShow(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(mapping: ['eventId' => 'eventId'])]
        Event $event,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $issue = $event->getIssue();
        if (!$issue instanceof Issue || $issue->getProject()?->getId() !== $project->getId()) {
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
