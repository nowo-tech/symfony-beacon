<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Form\ProjectReadTokenCreateType;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectReadTokenManager;
use Nowo\FormKitBundle\Form\Type\CsrfOnlyType;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Create/revoke project read API tokens from Settings. */
#[IsGranted('ROLE_USER')]
final class ProjectReadTokenController extends AbstractController
{
    public function __construct(
        private readonly ProjectReadTokenManager $tokenManager,
        private readonly ProjectReadTokenRepository $tokenRepository,
    ) {
    }

    #[Route('/projects/{id}/settings/read-tokens', name: 'project_read_token_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::SETTINGS_MANAGE, 'project')]
    public function create(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectReadTokenCreateType::class, null, [
            'csrf_token_id' => 'project_read_token_create',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'projects.read_token.invalid_csrf');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        /** @var array{label?: string|null} $data */
        $data = $form->getData();
        $created = $this->tokenManager->create($project, $user, (string) ($data['label'] ?? ''));
        $request->getSession()->set('_beacon_last_read_token', $created['rawToken']);
        $this->addFlash('success', 'projects.read_token.created');

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    #[Route('/projects/{id}/settings/read-tokens/{tokenId}/revoke', name: 'project_read_token_revoke', requirements: ['id' => Requirement::UUID, 'tokenId' => Requirement::UUID], methods: ['POST'])]
    #[IsGranted(ProjectPermission::SETTINGS_MANAGE, 'project')]
    public function revoke(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        string $tokenId,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_read_token_revoke',
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'projects.read_token.invalid_csrf');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $token = $this->tokenRepository->findOneBy(['uuid' => $tokenId, 'project' => $project]);
        if (!$token instanceof ProjectReadToken) {
            throw $this->createNotFoundException();
        }
        $this->tokenManager->revoke($token, $user);
        $this->addFlash('success', 'projects.read_token.revoked');

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }
}
