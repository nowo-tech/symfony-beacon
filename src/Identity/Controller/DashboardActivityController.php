<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\DashboardProductActivity;
use App\Identity\Entity\User;
use App\Identity\Repository\UserActionRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Cross-project recent product activity in the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardActivityController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UserActionRepository $userActionRepository,
    ) {
    }

    #[Route('/dashboard/activity', name: 'dashboard_activity', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = $this->resolveProjectFilter($accessible, $request->query->getString('project'));

        $uuids = [];
        if ($projectFilter instanceof Project) {
            $uuids = [$projectFilter->getUuid()];
        } else {
            foreach ($accessible as $project) {
                $uuids[] = $project->getUuid();
            }
        }

        $actions = $this->userActionRepository->findActorProductActivity(
            $user,
            DashboardProductActivity::types(),
            $uuids,
            50,
        );

        return $this->render('dashboard/activity.html.twig', [
            'actions' => $actions,
            'projects' => $accessible,
            'filters' => [
                'project' => $projectFilter?->getUuid() ?? '',
            ],
        ]);
    }

    /**
     * @param list<Project> $accessible
     */
    private function resolveProjectFilter(array $accessible, string $uuid): ?Project
    {
        if ('' === $uuid) {
            return null;
        }
        foreach ($accessible as $project) {
            if ($project->getUuid() === $uuid) {
                return $project;
            }
        }

        return null;
    }
}
