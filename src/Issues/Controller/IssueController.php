<?php

declare(strict_types=1);

namespace App\Issues\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueSavedView;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssuePriority;
use App\Issues\Enum\IssueStatus;
use App\Issues\Form\IssueIndexFilterType;
use App\Issues\Form\IssueSavedViewType;
use App\Issues\IssueListSort;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\Form\CsrfOnlyType;
use App\Shared\Form\GetFilterFormFactory;
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
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventRepository $eventRepository,
        private readonly IssueSearchRepository $issueSearchRepository,
        private readonly ProjectMembershipRepository $membershipRepository,
        private readonly ProductTourStepsBuilder $productTourStepsBuilder,
        private readonly ProjectAccessService $projectAccess,
        private readonly IssueSavedViewRepository $savedViewRepository,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly GetFilterFormFactory $getFilterFormFactory,
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
        $access = $this->projectAccess->requireMembership($project, $user);

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
        $filters = [
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
        ];
        $saveViewForm = $this->createForm(IssueSavedViewType::class, $this->filterQueryFromArray($filters), [
            'action' => $this->generateUrl('issue_view_save', ['id' => $project->getUuid()]),
            'method' => 'POST',
        ]);
        $deleteViewForms = [];
        foreach ($savedViews as $view) {
            $deleteViewForms[$view->getUuid()] = $this->createForm(CsrfOnlyType::class, null, [
                'action' => $this->generateUrl('issue_view_delete', [
                    'id' => $project->getUuid(),
                    'viewUuid' => $view->getUuid(),
                ]),
                'method' => 'POST',
                'csrf_token_id' => 'issue_view_delete',
            ])->createView();
        }

        $tourVars = $this->productTourStepsBuilder->twigVars(
            $this->productTourStepsBuilder->contextForProjectIssues($project, $user),
            $user,
            $request,
        );
        $memberChoices = [];
        foreach ($members as $member) {
            $memberId = $member->getId();
            if (null === $memberId) {
                continue;
            }

            $memberChoices[(string) $memberId] = $member->getDisplayName() ?: $member->getEmail();
        }

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'occurrenceByIssue' => $occurrenceByIssue,
            'members' => $members,
            'sort' => $sort,
            'compareResult' => $compareResult,
            'savedViews' => $savedViews,
            'saveViewForm' => $saveViewForm->createView(),
            'deleteViewForms' => $deleteViewForms,
            'pagination' => $pagination,
            'levels' => IssueLevel::values(),
            'filters' => $filters,
            'can_triage' => $access->canTriageIssues(),
            'filterForm' => $this->getFilterFormFactory->create(IssueIndexFilterType::class, $filters, [
                'action' => $this->generateUrl('issue_index', ['id' => $project->getUuid()]),
                'level_choices' => IssueLevel::values(),
                'member_choices' => $memberChoices,
            ])->createView(),
            ...$tourVars,
        ]);
    }

    /**
     * @return array{
     *     environmentA: string,
     *     environmentB: string,
     *     onlyA: list<Issue>,
     *     onlyB: list<Issue>,
     *     both: list<Issue>
     * }
     */
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
        $form = $this->createForm(IssueSavedViewType::class);
        $form->handleRequest($request);
        $submitted = $request->request->all($form->getName());
        $queryJson = \is_array($submitted) ? $this->filterQueryFromArray($submitted) : [];

        if (!$form->isSubmitted() || !$form->isValid()) {
            $name = \is_array($submitted) ? trim((string) ($submitted['name'] ?? '')) : '';
            $this->addFlash('error', '' === $name ? 'issues.view_name_empty' : 'issues.view_invalid');

            return $this->redirectToRoute('issue_index', ['id' => $project->getUuid()] + $queryJson);
        }

        $data = $form->getData();
        $name = trim((string) ((\is_array($data) ? $data['name'] : null) ?? ''));
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
            if (!\is_string($key) || !\in_array($key, IssueSavedViewType::QUERY_KEYS, true)) {
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
        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'issue_view_delete',
        ]);
        $form->submit($request->request->all());

        if (!$form->isSubmitted() || !$form->isValid()) {
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
     * @param array<string, mixed> $data
     *
     * @return array<string, int|string>
     */
    private function filterQueryFromArray(array $data): array
    {
        $query = [];
        foreach (IssueSavedViewType::QUERY_KEYS as $key) {
            if (!\array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
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
