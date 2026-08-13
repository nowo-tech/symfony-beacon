<?php

declare(strict_types=1);

namespace App\Ops\Controller;

use App\Ops\Form\AdminOpsOverviewFilterType;
use App\Ops\Service\OpsOverviewService;
use App\Ops\Service\SecurityPosture;
use App\Project\Repository\ProjectRepository;
use App\Shared\Form\GetFilterFormFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_ADMIN')]
final class AdminOpsOverviewController extends AbstractController
{
    public function __construct(
        private readonly OpsOverviewService $opsOverviewService,
        private readonly ProjectRepository $projectRepository,
        private readonly GetFilterFormFactory $getFilterFormFactory,
        private readonly SecurityPosture $securityPosture,
    ) {
    }

    #[Route('/admin/ops', name: 'admin_ops_overview', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $filterProject = null;
        $projectFilter = trim($request->query->getString('project'));
        if ('' !== $projectFilter && Uuid::isValid($projectFilter)) {
            $filterProject = $this->projectRepository->findOneBy(['uuid' => $projectFilter]);
        }

        $overview = $this->opsOverviewService->build($filterProject);
        $projectChoices = [];
        foreach ($overview['projects'] as $project) {
            $projectChoices[$project->getName()] = $project->getUuid();
        }

        return $this->render('admin/ops/overview.html.twig', [
            'overview' => $overview,
            'securityPostureItems' => $this->securityPosture->weakenedItems(),
            'filterForm' => $this->getFilterFormFactory->create(AdminOpsOverviewFilterType::class, [
                'project' => $projectFilter,
            ], [
                'action' => $this->generateUrl('admin_ops_overview'),
                'project_choices' => $projectChoices,
            ])->createView(),
        ]);
    }
}
