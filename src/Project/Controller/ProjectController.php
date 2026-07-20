<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Form\ProjectType;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\HumanFriendlyTokenGenerator;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectHistoryClearer;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Project CRUD, Settings (keys/members), and danger-zone clear/delete actions.
 */
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly ProjectHistoryClearer $historyClearer,
        private readonly HumanFriendlyTokenGenerator $tokenGenerator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/projects/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ProjectType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{name: string, description?: string|null} $data */
            $data = $form->getData();
            $name = trim((string) $data['name']);
            $description = trim((string) ($data['description'] ?? ''));

            $slugger = new AsciiSlugger();
            $slug = strtolower($slugger->slug($name)->toString());
            if ('' === $slug) {
                $slug = 'project-'.bin2hex(random_bytes(3));
            }
            if (null !== $this->projectRepository->findOneBy(['slug' => $slug])) {
                $slug .= '-'.bin2hex(random_bytes(2));
            }

            $project = new Project();
            $project->setName($name);
            $project->setSlug($slug);
            $project->setDescription('' !== $description ? $description : null);

            $membership = new ProjectMembership();
            $membership->setUser($user);
            $membership->setRole(ProjectRole::Owner);
            $project->addMembership($membership);

            $apiKey = $this->createApiKey($project, 'Default');
            $project->addApiKey($apiKey);

            $this->projectRepository->save($project);

            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/projects/{id}', name: 'project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Project $project): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        return $this->redirectToRoute('issue_index', ['id' => $project->getId()]);
    }

    #[Route('/projects/{id}/settings', name: 'project_settings', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function settings(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $membership = $this->projectAccess->requireMembership($project, $user);
        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->render('project/settings.html.twig', [
            'project' => $project,
            'membership' => $membership,
            'baseUrl' => $baseUrl,
            'labelAdjectives' => $this->tokenGenerator->adjectiveWordList(),
            'labelNouns' => $this->tokenGenerator->nounWordList(),
            'suggestedLabel' => $this->tokenGenerator->generateLabel(),
        ]);
    }

    #[Route('/projects/{id}/keys', name: 'project_keys_create', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function createKey(Project $project, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $label = trim($request->request->getString('label'));
        if ('' === $label) {
            $label = $this->tokenGenerator->generateLabel();
        }
        $key = $this->createApiKey($project, $label);
        $project->addApiKey($key);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.api_key_created');

        return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
    }

    #[Route('/projects/{id}/clear-history', name: 'project_clear_history', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function clearHistory(Project $project, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        if (!$this->isCsrfTokenValid('project_clear_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $projectId = $project->getId();
        $this->historyClearer->clear($project);

        $this->addFlash('success', 'flash.project.history_cleared');

        return $this->redirectToRoute('project_settings', ['id' => $projectId]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Project $project, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Owner);

        if (!$this->isCsrfTokenValid('project_delete_'.$project->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $confirmation = (string) $request->request->get('confirmation');
        if ($confirmation !== $project->getName()) {
            $this->addFlash('error', 'flash.project.delete_confirmation_mismatch');

            return $this->redirectToRoute('project_settings', ['id' => $project->getId()]);
        }

        // Clear telemetry first so SQLite (and ORM) stay consistent without relying on DB cascades alone.
        $this->historyClearer->clear($project);
        $project = $this->projectRepository->find($project->getId() ?? 0);
        if (!$project instanceof Project) {
            $this->addFlash('success', 'flash.project.deleted');

            return $this->redirectToRoute('dashboard_home');
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();

        $this->addFlash('success', 'flash.project.deleted');

        return $this->redirectToRoute('dashboard_home');
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
