<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Form\AdminProjectImportType;
use App\Project\Form\ProjectDeleteType;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AdminProjectShowPageBuilder;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectFactory;
use App\Project\Service\ProjectHistoryClearer;
use App\Project\Service\ProjectOpsStatsService;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\AdminSearchType;
use App\Shared\Form\CsrfOnlyFormFactory;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Http\SafeInternalRedirect;
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
 * Instance-admin project list, CRUD, ingest toggle, and view-as-member.
 */
#[IsGranted('ROLE_ADMIN')]
final class AdminProjectController extends AbstractController
{
    use RequiresValidFormTrait;

    public function __construct(
        private readonly UserActionRecorder $actionRecorder,
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectOpsStatsService $opsStats,
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectFactory $projectFactory,
        private readonly CsrfOnlyFormFactory $csrfOnlyFormFactory,
        private readonly GetFilterFormFactory $getFilterFormFactory,
        private readonly AdminProjectShowPageBuilder $showPageBuilder,
    ) {
    }

    /** List all projects on the instance (optional name/slug search). */
    #[Route('/admin/projects', name: 'admin_projects', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $query = $request->query->getString('q');
        $total = $this->projectRepository->countAllOrdered('' !== $query ? $query : null);
        $pagination = PagePagination::fromRequest($request, $total);
        $projects = $this->projectRepository->findAllOrdered(
            '' !== $query ? $query : null,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $projectIds = [];
        foreach ($projects as $project) {
            $id = $project->getId();
            if (null !== $id) {
                $projectIds[] = $id;
            }
        }

        return $this->render('admin/projects/index.html.twig', [
            'projects' => $projects,
            'q' => $query,
            'pagination' => $pagination,
            'searchForm' => $this->getFilterFormFactory->create(AdminSearchType::class, [
                'q' => $query,
            ], [
                'action' => $this->generateUrl('admin_projects'),
            ])->createView(),
            'opsStats' => $this->opsStats->forProjects($projects),
            'access_counts' => $this->projectRepository->countAccessByProjectIds($projectIds),
            'importForm' => $this->createForm(AdminProjectImportType::class, null, [
                'action' => $this->generateUrl('admin_projects_import'),
                'method' => 'POST',
                'csrf_token_id' => 'admin_projects_import',
            ])->createView(),
        ]);
    }

    /** Create a project (admin becomes owner; default API key). */
    #[Route('/admin/projects/new', name: 'admin_projects_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $form = $this->createForm(ProjectType::class, [
            'name' => '',
            'description' => '',
        ], [
            'csrf_token_id' => 'admin_project_new',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            /** @var User $actor */
            $actor = $this->getUser();
            $project = $this->createProject(
                trim($data['name']),
                (string) ($data['description'] ?? ''),
                $actor,
            );
            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
            'form' => $form,
            'project' => null,
            'is_edit' => false,
        ]);
    }

    /** Project detail: members, linked groups, open in product UI, delete. */
    #[Route('/admin/projects/{id}', name: 'admin_projects_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $actor */
        $actor = $this->getUser();

        return $this->render('admin/projects/show.html.twig', $this->showPageBuilder->build($project, $actor, $request));
    }

    /** Suspend or resume Envelope ingest for a project. */
    #[Route('/admin/projects/{id}/ingest', name: 'admin_projects_ingest_toggle', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function toggleIngest(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        $form = $this->csrfOnlyFormFactory->createWithFields(
            $this->generateUrl('admin_projects_ingest_toggle', ['id' => $project->getUuid()]),
            'admin_project_ingest_'.$project->getId(),
            ['enabled' => ''],
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        /** @var User $actor */
        $actor = $this->getUser();
        /** @var array{enabled?: string} $data */
        $data = $form->getData();
        $enable = '1' === ($data['enabled'] ?? '');
        $project->setIngestEnabled($enable);
        $this->actionRecorder->record(
            $enable ? UserActionType::ProjectResumed : UserActionType::ProjectSuspended,
            $actor,
            $actor,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
            ],
        );
        $this->entityManager->flush();
        $this->addFlash('success', $enable ? 'flash.admin_projects.ingest_resumed' : 'flash.admin_projects.ingest_suspended');

        return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
    }

    /** Enter view-as-member mode (ROLE_ADMIN effective role forced to Member). */
    #[Route('/admin/view-as-member/enable', name: 'admin_view_as_member_enable', methods: ['POST'])]
    public function enableViewAsMember(Request $request): RedirectResponse
    {
        $form = $this->csrfOnlyFormFactory->createWithFields(
            $this->generateUrl('admin_view_as_member_enable'),
            'admin_view_as_member_enable',
            ['project_uuid' => '', 'redirect' => ''],
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        /** @var User $actor */
        $actor = $this->getUser();
        /** @var array{project_uuid?: string, redirect?: string} $data */
        $data = $form->getData();
        $request->getSession()->set(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY, true);
        $context = [];
        $projectUuid = trim((string) ($data['project_uuid'] ?? ''));
        if ('' !== $projectUuid) {
            $project = $this->projectRepository->findOneBy(['uuid' => $projectUuid]);
            if ($project instanceof Project) {
                $context = [
                    'project_uuid' => $project->getUuid(),
                    'project_name' => $project->getName(),
                ];
            }
        }

        $this->actionRecorder->recordAndFlush(UserActionType::ProjectViewAsStarted, $actor, $actor, $context);
        $this->addFlash('success', 'flash.admin_projects.view_as_enabled');

        $fallback = $this->generateUrl('admin_projects');

        return $this->redirect(SafeInternalRedirect::resolve($request, (string) ($data['redirect'] ?? ''), $fallback));
    }

    /** Exit view-as-member mode. */
    #[Route('/admin/view-as-member/disable', name: 'admin_view_as_member_disable', methods: ['POST'])]
    public function disableViewAsMember(Request $request): RedirectResponse
    {
        $form = $this->csrfOnlyFormFactory->createWithFields(
            $this->generateUrl('admin_view_as_member_disable'),
            'admin_view_as_member_disable',
            ['redirect' => ''],
        );
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

        /** @var User $actor */
        $actor = $this->getUser();
        /** @var array{redirect?: string} $data */
        $data = $form->getData();
        $request->getSession()->remove(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY);
        $this->actionRecorder->recordAndFlush(UserActionType::ProjectViewAsEnded, $actor, $actor, []);
        $this->addFlash('success', 'flash.admin_projects.view_as_disabled');

        $fallback = $this->generateUrl('admin_projects');

        return $this->redirect(SafeInternalRedirect::resolve($request, (string) ($data['redirect'] ?? ''), $fallback));
    }

    /** Update project name and description. */
    #[Route('/admin/projects/{id}/edit', name: 'admin_projects_edit', requirements: ['id' => Requirement::UUID], methods: ['GET', 'POST'])]
    public function edit(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        $form = $this->createForm(ProjectType::class, [
            'name' => $project->getName(),
            'description' => $project->getDescription() ?? '',
        ], [
            'csrf_token_id' => 'admin_project_edit_'.$project->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            $project->setName(trim($data['name']));
            $description = trim((string) ($data['description'] ?? ''));
            $project->setDescription('' !== $description ? $description : null);
            $this->entityManager->flush();
            $this->addFlash('success', 'flash.admin_projects.updated');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        return $this->render('admin/projects/form.html.twig', [
            'form' => $form,
            'project' => $project,
            'is_edit' => true,
        ]);
    }

    /** Permanently delete a project (typed name confirmation; clears telemetry first). */
    #[Route('/admin/projects/{id}/delete', name: 'admin_projects_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        $form = $this->createForm(ProjectDeleteType::class, null, [
            'csrf_token_id' => 'admin_project_delete_'.$project->getId(),
            'project_id' => (int) $project->getId(),
            'input_id_prefix' => 'admin-project-delete-confirm-',
        ]);
        $form->handleRequest($request);
        $this->requireValidForm($form);

        $confirmation = (string) ($form->get('confirmation')->getData() ?? '');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

            return $this->redirectToRoute('admin_projects_show', ['id' => $project->getUuid()]);
        }

        /** @var User $actor */
        $actor = $this->getUser();
        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $projectId = $project->getId();
        $actorId = $actor->getId();

        $this->historyClearer->clear($project);

        $managedActor = null !== $actorId
            ? $this->entityManager->find(User::class, $actorId)
            : null;
        $project = null !== $projectId
            ? $this->projectRepository->find($projectId)
            : null;

        $this->actionRecorder->record(
            UserActionType::ProjectDeleted,
            $managedActor,
            $managedActor,
            [
                'project_uuid' => $projectUuid,
                'project_name' => $projectName,
            ],
        );

        if ($project instanceof Project) {
            $this->entityManager->remove($project);
        }
        $this->entityManager->flush();
        $this->addFlash('success', 'flash.project.deleted');

        return $this->redirectToRoute('admin_projects');
    }

    private function createProject(string $name, string $description, User $owner): Project
    {
        $project = $this->projectFactory->create($owner, $name, '' !== trim($description) ? trim($description) : null);

        $this->projectRepository->save($project, false);
        $this->actionRecorder->record(
            UserActionType::ProjectCreated,
            $owner,
            $owner,
            [
                'project_uuid' => $project->getUuid(),
                'project_name' => $project->getName(),
            ],
        );
        $this->entityManager->flush();

        return $project;
    }
}
