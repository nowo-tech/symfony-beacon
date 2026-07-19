<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Project\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $query = $request->query->getString('q');
        $projects = $this->projectRepository->findAccessibleByUser($user, '' !== $query ? $query : null);

        $statsPreview = [];
        foreach (\array_slice($projects, 0, 5) as $project) {
            $statsPreview[$project->getId() ?? 0] = $this->dailyProjectStatRepository->findLastDays($project, 7);
        }

        return $this->render('dashboard/home.html.twig', [
            'projects' => $projects,
            'query' => $query,
            'statsPreview' => $statsPreview,
        ]);
    }
}
