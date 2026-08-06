<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueSavedView;
use App\Issues\IssueListSort;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectAccessService;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Shared\Pagination\PagePagination;
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
 * Project issues list, filters, and saved views.
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
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly IssueSearchRepository $issueSearchRepository,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProductTourStepsBuilder $productTourStepsBuilder,
        private readonly ProjectAccessService $projectAccess,
        private readonly IssueSavedViewRepository $savedViewRepository,
        private readonly UserActionRecorder $userActionRecorder,
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

        $total = $this->issueSearchRepository->countSearch(
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
        $pagination = PagePagination::fromRequest($request, $total);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];

        $issues = $this->issueSearchRepository->search(
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
            $pagination['offset'],
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

        $tourVars = $this->productTourStepsBuilder->twigVars(
            $this->productTourStepsBuilder->contextForProjectIssues($project, $user),
            $user,
            $request,
        );

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'occurrenceByIssue' => $occurrenceByIssue,
            'members' => $members,
            'sort' => $sort,
            'compareResult' => $compareResult,
            'savedViews' => $savedViews,
            'pagination' => $pagination,
            'levels' => IssueLevel::values(),
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
            ...$tourVars,
        ]);
    }

    private function buildEnvironmentCompare(Project $project, string $environmentA, string $environmentB): array
    {
        $setA = $this->issueSearchRepository->findByLastEnvironment($project, $environmentA);
        $setB = $this->issueSearchRepository->findByLastEnvironment($project, $environmentB);

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

    #[Route('/projects/{id}/issues/views', name: 'issue_view_save', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function saveView(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireTriage($project, $user);

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
        $this->projectAccess->requireTriage($project, $user);

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

}
