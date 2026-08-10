<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectHistoryClearer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project danger zone: clear history and delete.
 */
#[IsGranted('ROLE_USER')]
final class ProjectDangerZoneController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectRepository $projectRepository,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/clear-history', name: 'project_clear_history', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function clearHistory(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SETTINGS_MANAGE);

        if (!$this->isCsrfTokenValid('project_clear_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $userId = $user->getId();
        $this->historyClearer->clear($project);

        $managedUser = null !== $userId
            ? $this->entityManager->find(User::class, $userId)
            : null;
        $this->userActionRecorder->recordAndFlush(
            UserActionType::ProjectHistoryCleared,
            $managedUser,
            $managedUser,
            [
                'project_uuid' => $projectUuid,
                'project_name' => $projectName,
            ],
        );

        $this->addFlash('success', 'flash.project.history_cleared');

        return $this->redirectToRoute('project_settings', ['id' => $projectUuid]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::DELETE);

        if (!$this->isCsrfTokenValid('project_delete_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $confirmation = (string) $request->request->get('confirmation');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        // Clear telemetry first so SQLite (and ORM) stay consistent without relying on DB cascades alone.
        $projectUuid = $project->getUuid();
        $projectName = $project->getName();
        $projectId = $project->getId();
        $userId = $user->getId();
        $this->historyClearer->clear($project);

        $managedUser = null !== $userId
            ? $this->entityManager->find(User::class, $userId)
            : null;
        $project = null !== $projectId
            ? $this->projectRepository->find($projectId)
            : null;

        $this->userActionRecorder->record(
            UserActionType::ProjectDeleted,
            $managedUser,
            $managedUser,
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

        return $this->redirectToRoute('dashboard_home');
    }
}
