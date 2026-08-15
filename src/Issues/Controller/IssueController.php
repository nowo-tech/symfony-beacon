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
use App\Issues\Form\IssueIndexFilterType;
use App\Issues\Form\IssueSavedViewType;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueSavedViewRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\IssueIndexFilterResolver;
use App\Project\Entity\Project;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use Nowo\FormKitBundle\Form\Type\CsrfOnlyType;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
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
        private readonly IssueIndexFilterResolver $issueIndexFilterResolver,
        private readonly ProductTourStepsBuilder $productTourStepsBuilder,
        private readonly ProjectAccessService $projectAccess,
        private readonly IssueSavedViewRepository $savedViewRepository,
        private readonly UserActionRecorder $userActionRecorder,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/projects/{id}/issues', name: 'issue_index', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    #[IsGranted(ProjectPermission::VIEW, 'project')]
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

        $resolved = $this->issueIndexFilterResolver->resolve($project, $request);

        $total = $this->issueSearchRepository->countSearch(
            $project,
            $resolved->query,
            $resolved->level,
            $resolved->status,
            $resolved->environment,
            $resolved->release,
            $resolved->priority,
            $resolved->assignee,
            $resolved->unassignedOnly,
            tag: $resolved->tag,
            url: $resolved->url,
            user: $resolved->user,
        );
        $pagination = PagePagination::fromRequest($request, $total);
        $page = $pagination['page'];
        $perPage = $pagination['per_page'];

        $issues = $this->issueSearchRepository->search(
            $project,
            $resolved->query,
            $resolved->level,
            $resolved->status,
            $resolved->environment,
            $resolved->release,
            $resolved->priority,
            $resolved->assignee,
            $resolved->unassignedOnly,
            $resolved->sort,
            $perPage,
            $pagination['offset'],
            tag: $resolved->tag,
            url: $resolved->url,
            user: $resolved->user,
        );
        $occurrenceByIssue = $this->eventRepository->occurrenceStatsForIssues($issues);

        $compareResult = null;
        if (null !== $resolved->compare && null !== $resolved->environment) {
            $compareResult = $this->buildEnvironmentCompare($project, $resolved->environment, $resolved->compare);
        }

        $savedViews = $this->savedViewRepository->findForUserAndProject($user, $project);
        $filters = $resolved->formData($page, $perPage);
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

        return $this->render('issue/index.html.twig', [
            'project' => $project,
            'issues' => $issues,
            'occurrenceByIssue' => $occurrenceByIssue,
            'members' => $resolved->members,
            'sort' => $resolved->sort,
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
                'member_choices' => $resolved->memberChoices(),
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
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
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
        /** @var array<string, mixed> $submitted */
        $submitted = $request->request->all($form->getName());
        $queryJson = $this->filterQueryFromArray($submitted);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $name = trim((string) ($submitted['name'] ?? ''));
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
    #[IsGranted(ProjectPermission::VIEW, 'project')]
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
    #[IsGranted(ProjectPermission::ISSUES_TRIAGE, 'project')]
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
