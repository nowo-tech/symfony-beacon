<?php

declare(strict_types=1);

namespace App\Analytics\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
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
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/analytics', name: 'analytics_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::AnalyticsOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $stats = $this->dailyProjectStatRepository->findLastDays($project, 30);

        return $this->render('analytics/show.html.twig', [
            'project' => $project,
            'stats' => $stats,
        ]);
    }
}
