<?php

declare(strict_types=1);

namespace App\Analytics\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Renders per-project daily analytics (errors, transactions, N+1).
 */
#[IsGranted('ROLE_USER')]
final class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route('/projects/{id}/analytics', name: 'analytics_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Project $project): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $stats = $this->dailyProjectStatRepository->findLastDays($project, 30);

        return $this->render('analytics/show.html.twig', [
            'project' => $project,
            'stats' => $stats,
        ]);
    }
}
