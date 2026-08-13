<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Form\ProjectClearHistoryType;
use App\Project\Form\ProjectDeleteType;
use App\Project\Repository\ProjectRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectHistoryClearer;
use App\Shared\Controller\RequiresValidFormTrait;
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
    use RequiresValidFormTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly ProjectRepository $projectRepository,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/clear-history', name: 'project_clear_history', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::SETTINGS_MANAGE, 'project')]
    public function clearHistory(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectClearHistoryType::class, null, [
            'csrf_token_id' => 'project_clear_'.$project->getId(),
        ]);
        $form->handleRequest($request);
        $this->requireValidCsrfForm($form);

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

        return $this->redirectToRoute('project_settings_section', ['id' => $projectUuid, 'section' => ProjectSettingsSection::Danger->value]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::DELETE, 'project')]
    public function delete(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectDeleteType::class, null, [
            'csrf_token_id' => 'project_delete_'.$project->getId(),
            'project_id' => (int) $project->getId(),
            'confirmation_value' => $project->getName(),
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted()) {
            $this->requireValidCsrfForm($form);
        }
        if (!$form->isValid()) {
            if (!$form->get('confirmation')->isValid()) {
                $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

                return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Danger->value]);
            }

            $this->requireValidCsrfForm($form);
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
