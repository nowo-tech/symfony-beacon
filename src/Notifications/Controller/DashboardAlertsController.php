<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Form\DashboardAlertsFilterType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\AccessibleProjectFilter;
use App\Shared\Form\GetFilterFormFactory;
use App\Shared\Pagination\PagePagination;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Failed notification deliveries across accessible projects (dashboard Alerts).
 */
#[IsGranted('ROLE_USER')]
final class DashboardAlertsController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/alerts', name: 'dashboard_alerts', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));
        $projects = $projectFilter instanceof Project ? [$projectFilter] : $accessible;

        $total = $this->destinationRepository->countWithFailedLastDeliveryInProjects($projects);
        $pagination = PagePagination::fromRequest($request, $total);
        $destinations = $this->destinationRepository->findWithFailedLastDeliveryInProjects(
            $projects,
            $pagination['per_page'],
            $pagination['offset'],
        );

        $projectChoices = AccessibleProjectFilter::choiceMap($accessible);

        return $this->render('dashboard/alerts.html.twig', [
            'destinations' => $destinations,
            'projects' => $accessible,
            'filters' => [
                'project' => $projectFilter?->getUuid() ?? '',
                'per_page' => $pagination['per_page'],
                'page' => 1,
            ],
            'filterForm' => $this->getFilterFormFactory->create(DashboardAlertsFilterType::class, [
                'project' => $projectFilter?->getUuid() ?? '',
                'per_page' => $pagination['per_page'],
                'page' => 1,
            ], [
                'action' => $this->generateUrl('dashboard_alerts'),
                'project_choices' => $projectChoices,
            ])->createView(),
            'pagination' => $pagination,
        ]);
    }
}
