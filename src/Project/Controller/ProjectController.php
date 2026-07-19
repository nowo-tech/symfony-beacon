<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectApiKey;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectAccessService;
use App\Shared\ProjectRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[IsGranted('ROLE_USER')]
#[Route('/projects')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly ProjectAccessService $projectAccess,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/new', name: 'project_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $name = trim($request->request->getString('name'));
            $description = trim($request->request->getString('description'));
            if ('' === $name) {
                $this->addFlash('error', 'Project name is required.');

                return $this->render('project/new.html.twig');
            }

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

            $apiKey = ProjectApiKey::generate($project, 'Default');
            $project->addApiKey($apiKey);

            $this->projectRepository->save($project);

            $this->addFlash('success', 'Project created.');

            return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig');
    }

    #[Route('/{id}', name: 'project_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $membership = $this->projectAccess->requireMembership($project, $user);
        $baseUrl = $request->getSchemeAndHttpHost();

        return $this->render('project/show.html.twig', [
            'project' => $project,
            'membership' => $membership,
            'baseUrl' => $baseUrl,
        ]);
    }

    #[Route('/{id}/keys', name: 'project_keys_create', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function createKey(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireRole($project, $user, ProjectRole::Admin);

        $label = trim($request->request->getString('label')) ?: 'API key';
        $key = ProjectApiKey::generate($project, $label);
        $project->addApiKey($key);
        $this->entityManager->flush();

        $this->addFlash('success', 'API key created.');

        return $this->redirectToRoute('project_show', ['id' => $project->getId()]);
    }
}
