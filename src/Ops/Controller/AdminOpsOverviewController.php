<?php

declare(strict_types=1);

namespace App\Ops\Controller;

use App\Project\Repository\ProjectRepository;
use App\Ops\Service\OpsOverviewService;
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

        return $this->render('admin/ops/overview.html.twig', [
            'overview' => $this->opsOverviewService->build($filterProject),
        ]);
    }
}
