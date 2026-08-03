<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Issues\AssignmentScope;
use App\Issues\Repository\IssueMentionRepository;
use App\Issues\Repository\IssueRepository;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Repository\ProjectRepository;
use App\Shared\IssueStatus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Lightweight day / inbox summary cards for the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardSummaryController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly IssueRepository $issueRepository,
        private readonly IssueMentionRepository $mentionRepository,
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
    ) {
    }

    #[Route('/dashboard/summary', name: 'dashboard_summary', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);

        $mineOpen = $this->issueRepository->countAssignments(
            $accessible,
            AssignmentScope::Mine,
            $user,
            status: IssueStatus::Unresolved,
        );
        $unassignedOpen = $this->issueRepository->countAssignments(
            $accessible,
            AssignmentScope::Unassigned,
            $user,
            status: IssueStatus::Unresolved,
        );
        $unreadMentions = $this->mentionRepository->countInboxForUser($user, $accessible, true);
        $failedDeliveries = $this->destinationRepository->countWithFailedLastDeliveryInProjects($accessible);

        $errorsToday = 0;
        foreach ($this->dailyProjectStatRepository->findLastDaysForProjects($accessible, 1) as $days) {
            foreach ($days as $stat) {
                $errorsToday += $stat->getErrorCount();
            }
        }

        return $this->render('dashboard/summary.html.twig', [
            'summary' => [
                'mine_open' => $mineOpen,
                'unassigned_open' => $unassignedOpen,
                'unread_mentions' => $unreadMentions,
                'failed_deliveries' => $failedDeliveries,
                'errors_today' => $errorsToday,
                'project_count' => \count($accessible),
            ],
        ]);
    }
}
