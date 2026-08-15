<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\DashboardProductActivity;
use App\Identity\Entity\User;
use App\Identity\Form\DashboardActivityFilterType;
use App\Identity\Repository\UserActionRepository;
use App\Identity\Service\DashboardActivityFilterResolver;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use App\Shared\Pagination\PagePagination;
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
        private readonly UserActionRepository $userActionRepository,
        private readonly DashboardActivityFilterResolver $filterResolver,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/activity', name: 'dashboard_activity', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $filters = $this->filterResolver->resolve($user, $request);

        $types = DashboardProductActivity::types();
        $total = $this->userActionRepository->countActorProductActivity($user, $types, $filters->projectUuids);
        $pagination = PagePagination::fromRequest($request, $total);
        $actions = $this->userActionRepository->findActorProductActivity(
            $user,
            $types,
            $filters->projectUuids,
            $pagination['per_page'],
            $pagination['offset'],
        );
        $formData = $filters->formData($pagination['per_page']);

        return $this->render('dashboard/activity.html.twig', [
            'actions' => $actions,
            'projects' => $filters->accessibleProjects,
            'filters' => $formData,
            'filterForm' => $this->getFilterFormFactory->create(DashboardActivityFilterType::class, $formData, [
                'action' => $this->generateUrl('dashboard_activity'),
                'project_choices' => $filters->projectChoices(),
            ])->createView(),
            'pagination' => $pagination,
        ]);
    }
}
