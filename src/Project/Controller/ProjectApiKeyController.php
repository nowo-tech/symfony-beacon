<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Form\ProjectApiKeyCreateType;
use App\Project\Security\ProjectPermission;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectApiKeyFactory;
use App\Shared\Controller\RequiresValidFormTrait;
use App\Shared\Form\CsrfOnlyType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project API key create / revoke / rotate.
 */
#[IsGranted('ROLE_USER')]
final class ProjectApiKeyController extends AbstractController
{
    use RequiresValidFormTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectApiKeyFactory $projectApiKeyFactory,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/keys', name: 'project_keys_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::API_KEYS_MANAGE, 'project')]
    public function createKey(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectApiKeyCreateType::class, null, [
            'csrf_token_id' => 'project_key_create_'.$project->getId(),
        ]);
        $form->handleRequest($request);
        $this->requireValidForm($form);

        /** @var array{label?: string|null} $data */
        $data = $form->getData();
        $label = trim((string) ($data['label'] ?? ''));
        if ('' === $label) {
            $label = $this->tokenGenerator->generateLabel();
        }
        $key = $this->projectApiKeyFactory->create($project, $label);
        $project->addApiKey($key);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyCreated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $plain = $key->consumeIssuedPlainSecret() ?? '';
        $this->addFlash('success', 'flash.project.api_key_created');
        $request->getSession()->set('_beacon_last_api_key_dsn', $key->buildDsn($this->settingsBaseUrl($request), $plain));

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/revoke',
        name: 'project_keys_revoke',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::API_KEYS_MANAGE, 'project')]
    public function revokeKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertKeyBelongsToProject($apiKey, $project);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_key_revoke_'.$apiKey->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidCsrfForm($form);

        $apiKey->setActive(false);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRevoked, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $apiKey->getLabel(),
        ]);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_revoked');

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    #[Route(
        '/projects/{projectId}/keys/{keyId}/rotate',
        name: 'project_keys_rotate',
        requirements: ['projectId' => Requirement::UUID, 'keyId' => '\d+'],
        methods: ['POST'],
    )]
    #[IsGranted(ProjectPermission::API_KEYS_MANAGE, 'project')]
    public function rotateKey(
        #[MapEntity(mapping: ['projectId' => 'uuid'])]
        Project $project,
        #[MapEntity(id: 'keyId')]
        ProjectApiKey $apiKey,
        Request $request,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->assertKeyBelongsToProject($apiKey, $project);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_key_rotate_'.$apiKey->getId(),
        ]);
        $form->submit($request->request->all());
        $this->requireValidCsrfForm($form);

        $label = $apiKey->getLabel();
        $apiKey->setActive(false);
        $newKey = $this->projectApiKeyFactory->create($project, $label);
        $project->addApiKey($newKey);
        $this->userActionRecorder->record(UserActionType::ProjectApiKeyRotated, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
            'label' => $label,
        ]);
        $this->entityManager->flush();

        $plain = $newKey->consumeIssuedPlainSecret() ?? '';
        $this->addFlash('success', 'flash.project.api_key_rotated');
        $request->getSession()->set('_beacon_last_api_key_dsn', $newKey->buildDsn($this->settingsBaseUrl($request), $plain));

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    private function assertKeyBelongsToProject(ProjectApiKey $apiKey, Project $project): void
    {
        if ($apiKey->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
    }

    private function settingsBaseUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }
}
