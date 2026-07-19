<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use App\Shared\IssueStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class IssueController extends AbstractController
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EventRepository $eventRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route('/projects/{id}/issues', name: 'issue_index', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function index(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $statusParam = $request->query->getString('status');
        $status = '' !== $statusParam
            ? (IssueStatus::tryFrom($statusParam) ?? IssueStatus::Unresolved)
            : IssueStatus::Unresolved;

        $issues = $this->issueRepository->search(
            $project,
            $request->query->getString('q') ?: null,
            $request->query->getString('level') ?: null,
            $status,
            $request->query->getString('environment') ?: null,
        );

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'filters' => [
                'q' => $request->query->getString('q'),
                'level' => $request->query->getString('level'),
                'status' => $status->value,
                'environment' => $request->query->getString('environment'),
            ],
        ]);
    }

    #[Route('/projects/{projectId}/issues/{id}', name: 'issue_show', methods: ['GET'], requirements: ['projectId' => '\d+', 'id' => '\d+'])]
    public function show(int $projectId, Issue $issue): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $issue->getProject();
        if (null === $project || $project->getId() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        $events = $this->eventRepository->findLatestForIssue($issue);

        return $this->render('issue/show.html.twig', [
            'project' => $project,
            'issue' => $issue,
            'events' => $events,
            'latestEvent' => $events[0] ?? null,
        ]);
    }

    #[Route('/projects/{projectId}/events/{eventId}', name: 'event_show', methods: ['GET'], requirements: ['projectId' => '\d+'])]
    public function eventShow(int $projectId, string $eventId): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $event = $this->eventRepository->findOneByEventId($eventId);
        if (null === $event || null === $event->getIssue()?->getProject() || $event->getIssue()->getProject()->getId() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($event->getIssue()->getProject(), $user);

        return $this->render('issue/event.html.twig', [
            'project' => $event->getIssue()->getProject(),
            'issue' => $event->getIssue(),
            'event' => $event,
        ]);
    }
}
