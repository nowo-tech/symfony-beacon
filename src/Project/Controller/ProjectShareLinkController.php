<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectSettingsSection;
use App\Project\Entity\ProjectShareLink;
use App\Project\Form\ProjectShareCreateType;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectShareLinkManager;
use App\Shared\Form\CsrfOnlyType;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Create/revoke share links (Settings) and open /share/{token}.
 */
final class ProjectShareLinkController extends AbstractController
{
    public function __construct(
        private readonly ProjectShareLinkManager $shareLinkManager,
        private readonly ProjectShareLinkRepository $shareLinkRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly IssueRepository $issueRepository,
    ) {
    }

    #[Route('/share/{token}', name: 'project_share_open', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET'])]
    public function open(Request $request, string $token): RedirectResponse
    {
        $link = $this->shareLinkManager->findUsableByRawToken($token);
        if (!$link instanceof ProjectShareLink) {
            $this->addFlash('error', 'projects.share.invalid');

            return $this->redirectToRoute('nowo_auth_kit_login');
        }

        $user = $this->getUser();
        if (!$user instanceof User) {
            $shareUrl = $this->generateUrl('project_share_open', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            if ($request->hasSession()) {
                $request->getSession()->set('_security.main.target_path', $shareUrl);
            }

            return $this->redirectToRoute('nowo_auth_kit_login');
        }

        try {
            $this->shareLinkManager->consume($link, $user);
        } catch (RuntimeException $e) {
            if (!\in_array($e->getMessage(), ['share_exhausted', 'missing_project', 'issue_wrong_project'], true)) {
                throw $e;
            }
            $this->addFlash('error', 'projects.share.invalid');

            return $this->redirectToRoute('nowo_auth_kit_login');
        }
        $project = $link->getProject();
        $issue = $link->getIssue();
        if ($issue instanceof Issue && $project instanceof Project) {
            return $this->redirectToRoute('issue_show', [
                'projectId' => $project->getUuid(),
                'id' => $issue->getUuid(),
            ]);
        }

        return $this->redirectToRoute('issue_index', ['id' => $project?->getUuid()]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/projects/{id}/settings/share-links', name: 'project_share_create', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function create(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SHARE_LINKS_MANAGE);

        $form = $this->createForm(ProjectShareCreateType::class, null, [
            'csrf_token_id' => 'project_share_create',
        ]);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'projects.share.invalid_csrf');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        /** @var array{days?: int|null, max_uses?: int|null, issue_uuid?: string|null} $data */
        $data = $form->getData();
        $days = max(1, min(30, (int) ($data['days'] ?? 7)));
        $expiresAt = new DateTimeImmutable(\sprintf('+%d days', $days));
        $maxUses = $data['max_uses'] ?? null;
        if (null !== $maxUses) {
            $maxUses = min(10_000, (int) $maxUses);
        }
        $issueUuid = trim((string) ($data['issue_uuid'] ?? ''));
        $issue = null;
        if ('' !== $issueUuid) {
            $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid, 'project' => $project]);
            if (!$issue instanceof Issue) {
                $this->addFlash('error', 'projects.share.issue_not_found');

                return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
            }
        }

        try {
            $created = $this->shareLinkManager->create($project, $user, $issue, $expiresAt, $maxUses);
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', 'projects.share.'.$e->getMessage());

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $url = $this->generateUrl('project_share_open', ['token' => $created['rawToken']], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->addFlash('success', 'projects.share.created');
        $request->getSession()->set('_beacon_last_share_url', $url);

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }

    #[IsGranted('ROLE_USER')]
    #[Route('/projects/{id}/settings/share-links/{shareId}/revoke', name: 'project_share_revoke', requirements: ['id' => Requirement::UUID, 'shareId' => Requirement::UUID], methods: ['POST'])]
    public function revoke(
        Request $request,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        string $shareId,
    ): RedirectResponse {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requirePermission($project, $user, ProjectPermission::SHARE_LINKS_MANAGE);

        $form = $this->createForm(CsrfOnlyType::class, null, [
            'csrf_token_id' => 'project_share_revoke',
        ]);
        $form->submit($request->request->all());
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'projects.share.invalid_csrf');

            return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
        }

        $link = $this->shareLinkRepository->findOneBy(['uuid' => $shareId, 'project' => $project]);
        if (null === $link) {
            throw $this->createNotFoundException();
        }
        $this->shareLinkManager->revoke($link, $user);
        $this->addFlash('success', 'projects.share.revoked');

        return $this->redirectToRoute('project_settings_section', ['id' => $project->getUuid(), 'section' => ProjectSettingsSection::Access->value]);
    }
}
