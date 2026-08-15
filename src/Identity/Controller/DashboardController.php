<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Analytics\Repository\DailyProjectStatRepository;
use App\Identity\Entity\User;
use App\Identity\Form\DashboardProjectSearchType;
use App\Identity\Service\ProductTourStepsBuilder;
use App\Project\Service\AccessibleProjectsProvider;
use App\Shared\Settings\Repository\InstanceSettingsRepository;
use Nowo\FormKitBundle\Form\GetFilterFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Authenticated home listing projects the user can access.
 */
#[IsGranted('ROLE_USER')]
final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly AccessibleProjectsProvider $accessibleProjects,
        private readonly DailyProjectStatRepository $dailyProjectStatRepository,
        private readonly InstanceSettingsRepository $instanceSettingsRepository,
        private readonly ProductTourStepsBuilder $productTourStepsBuilder,
        private readonly GetFilterFormFactory $getFilterFormFactory,
    ) {
    }

    #[Route('/dashboard', name: 'dashboard_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $query = $request->query->getString('q');
        $projects = $this->accessibleProjects->forUser($user, '' !== $query ? $query : null);

        $previewProjects = \array_slice($projects, 0, 5);
        $statsPreview = $this->dailyProjectStatRepository->findLastDaysForProjects($previewProjects, 7);

        $setupCompleted = $this->instanceSettingsRepository->getOrCreate()->isSetupCompleted();
        $showSetupBanner = $this->isGranted('ROLE_ADMIN') && !$setupCompleted;

        $tourVars = $this->productTourStepsBuilder->twigVars(
            $this->productTourStepsBuilder->contextForDashboard(),
            $user,
            $request,
        );

        return $this->render('dashboard/home.html.twig', [
            'projects' => $projects,
            'query' => $query,
            'searchForm' => $this->getFilterFormFactory->create(DashboardProjectSearchType::class, [
                'q' => $query,
            ], [
                'action' => $this->generateUrl('dashboard_home'),
            ])->createView(),
            'statsPreview' => $statsPreview,
            'openNewProject' => $request->query->getBoolean('new'),
            'showSetupBanner' => $showSetupBanner,
            ...$tourVars,
        ]);
    }
}
