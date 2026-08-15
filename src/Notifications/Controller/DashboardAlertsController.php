<?php

declare(strict_types=1);

namespace App\Notifications\Controller;

use App\Identity\Entity\User;
use App\Notifications\Form\DashboardAlertsFilterType;
use App\Notifications\Repository\NotificationDestinationRepository;
use App\Notifications\Service\DashboardAlertsFilterResolver;
use App\Shared\Pagination\PagePagination;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
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
        private readonly NotificationDestinationRepository $destinationRepository,
        private readonly DashboardAlertsFilterResolver $filterResolver,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/alerts', name: 'dashboard_alerts', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filters = $this->filterResolver->resolve($user, $request);

        $total = $this->destinationRepository->countWithFailedLastDeliveryInProjects($filters->selectedProjects);
        $pagination = PagePagination::fromRequest($request, $total);
        $destinations = $this->destinationRepository->findWithFailedLastDeliveryInProjects(
            $filters->selectedProjects,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $formData = $filters->formData($pagination['per_page']);

        return $this->render('dashboard/alerts.html.twig', [
            'destinations' => $destinations,
            'projects' => $filters->accessibleProjects,
            'filters' => $formData,
            'filterForm' => $this->getFilterFormFactory->create(DashboardAlertsFilterType::class, $formData, [
                'action' => $this->generateUrl('dashboard_alerts'),
                'project_choices' => $filters->projectChoices(),
            ])->createView(),
            'pagination' => $pagination,
        ]);
    }
}
