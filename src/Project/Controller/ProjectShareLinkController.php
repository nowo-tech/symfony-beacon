<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectShareLink;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectShareLinkManager;
use App\Shared\ProjectRole;
use DateTimeImmutable;
use InvalidArgumentException;
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

        $this->shareLinkManager->consume($link, $user);
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
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_share_create', $request->request->getString('_token'))) {
            $this->addFlash('error', 'projects.share.invalid_csrf');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $days = max(1, min(30, $request->request->getInt('days', 7)));
        $expiresAt = new DateTimeImmutable(\sprintf('+%d days', $days));
        $issueUuid = trim($request->request->getString('issue_uuid'));
        $issue = null;
        if ('' !== $issueUuid) {
            $issue = $this->issueRepository->findOneBy(['uuid' => $issueUuid, 'project' => $project]);
            if (!$issue instanceof Issue) {
                $this->addFlash('error', 'projects.share.issue_not_found');

                return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
            }
        }

        try {
            $created = $this->shareLinkManager->create($project, $user, $issue, $expiresAt);
        } catch (InvalidArgumentException $e) {
            $this->addFlash('error', 'projects.share.'.$e->getMessage());

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $url = $this->generateUrl('project_share_open', ['token' => $created['rawToken']], UrlGeneratorInterface::ABSOLUTE_URL);
        $this->addFlash('success', 'projects.share.created');
        $request->getSession()->set('_beacon_last_share_url', $url);

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
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
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_share_revoke', $request->request->getString('_token'))) {
            $this->addFlash('error', 'projects.share.invalid_csrf');

            return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
        }

        $link = $this->shareLinkRepository->findOneBy(['uuid' => $shareId, 'project' => $project]);
        if (null === $link) {
            throw $this->createNotFoundException();
        }
        $this->shareLinkManager->revoke($link, $user);
        $this->addFlash('success', 'projects.share.revoked');

        return $this->redirectToRoute('project_settings', ['id' => $project->getUuid()]);
    }
}
