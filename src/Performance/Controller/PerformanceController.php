<?php

declare(strict_types=1);

namespace App\Performance\Controller;

use App\Identity\Entity\User;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Project performance transactions list/detail with optional N+1 filter.
 */
#[IsGranted('ROLE_USER')]
final class PerformanceController extends AbstractController
{
    public function __construct(
        private readonly PerfTransactionRepository $transactionRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route('/projects/{id}/performance', name: 'performance_index', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function index(Project $project, Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $nPlusOneOnly = $request->query->getBoolean('nplus1');
        $transactions = $this->transactionRepository->findForProject($project, $nPlusOneOnly);

        return $this->render('performance/index.html.twig', [
            'project' => $project,
            'transactions' => $transactions,
            'nPlusOneOnly' => $nPlusOneOnly,
        ]);
    }

    #[Route('/projects/{projectId}/performance/{id}', name: 'performance_show', requirements: ['projectId' => '\d+', 'id' => '\d+'], methods: ['GET'])]
    public function show(int $projectId, PerfTransaction $transaction): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $project = $transaction->getProject();
        if (!$project instanceof Project || $project->getId() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        return $this->render('performance/show.html.twig', [
            'project' => $project,
            'transaction' => $transaction,
        ]);
    }
}
