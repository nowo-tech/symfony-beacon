<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Access\ProjectAccess;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Enum\ProjectRole;
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
 * Project API key create / revoke / rotate.
 */
#[IsGranted('ROLE_USER')]
final class ProjectApiKeyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectAccessService $projectAccess,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/keys', name: 'project_keys_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function createKey(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_key_create_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $label = trim($request->request->getString('label'));
        if ('' === $label) {
            $label = $this->tokenGenerator->generateLabel();
        }
        $key = $this->createApiKey($project, $label);
        $project->addApiKey($key);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyCreated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_created');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/revoke',
        name: 'project_keys_revoke',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    public function revokeKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);
        $this->assertKeyBelongsToProject($apiKey, $project);

        if (!$this->isCsrfTokenValid('project_key_revoke_'.$apiKey->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $apiKey->setActive(false);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRevoked, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $apiKey->getLabel(),
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_revoked');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/rotate',
        name: 'project_keys_rotate',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    public function rotateKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);
        $this->assertKeyBelongsToProject($apiKey, $project);

        if (!$this->isCsrfTokenValid('project_key_rotate_'.$apiKey->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $label = $apiKey->getLabel();
        $apiKey->setActive(false);
        $newKey = $this->createApiKey($project, $label);
        $project->addApiKey($newKey);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRotated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_rotated');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }

    private function assertKeyBelongsToProject(ProjectApiKey $apiKey, Project $project): void
    {
        if ($apiKey->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
    }

    private function createApiKey(Project $project, string $label): ProjectApiKey
    {
        for ($attempt = 0; $attempt < 8; ++$attempt) {
            $publicKey = $this->tokenGenerator->generateKey();
            if (null === $this->entityManager->getRepository(ProjectApiKey::class)->findOneBy(['publicKey' => $publicKey])) {
                return ProjectApiKey::generate($project, $label, $publicKey);
            }
        }

        return ProjectApiKey::generate($project, $label, $this->tokenGenerator->generateKey(4));
    }

}
