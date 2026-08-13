<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\DashboardProductActivity;
use App\Identity\Entity\User;
use App\Identity\Form\DashboardActivityFilterType;
use App\Identity\Repository\UserActionRepository;
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
 * Cross-project recent product activity in the Dashboard section.
 */
#[IsGranted('ROLE_USER')]
final class DashboardActivityController extends AbstractController
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly UserActionRepository $userActionRepository,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard/activity', name: 'dashboard_activity', methods: ['GET'])]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $accessible = $this->projectRepository->findAccessibleByUser($user);
        $projectFilter = AccessibleProjectFilter::resolve($accessible, $request->query->getString('project'));

        $uuids = [];
        if ($projectFilter instanceof Project) {
            $uuids = [$projectFilter->getUuid()];
        } else {
            foreach ($accessible as $project) {
                $uuids[] = $project->getUuid();
            }
        }

        $types = DashboardProductActivity::types();
        $total = $this->userActionRepository->countActorProductActivity($user, $types, $uuids);
        $pagination = PagePagination::fromRequest($request, $total);
        $actions = $this->userActionRepository->findActorProductActivity(
            $user,
            $types,
            $uuids,
            $pagination['per_page'],
            $pagination['offset'],
        );

        $projectChoices = AccessibleProjectFilter::choiceMap($accessible);

        return $this->render('dashboard/activity.html.twig', [
            'actions' => $actions,
            'projects' => $accessible,
            'filters' => [
                'project' => $projectFilter?->getUuid() ?? '',
                'per_page' => $pagination['per_page'],
                'page' => 1,
            ],
            'filterForm' => $this->getFilterFormFactory->create(DashboardActivityFilterType::class, [
                'project' => $projectFilter?->getUuid() ?? '',
                'per_page' => $pagination['per_page'],
                'page' => 1,
            ], [
                'action' => $this->generateUrl('dashboard_activity'),
                'project_choices' => $projectChoices,
            ])->createView(),
            'pagination' => $pagination,
        ]);
    }
}
