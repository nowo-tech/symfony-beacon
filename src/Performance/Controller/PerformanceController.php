<?php

declare(strict_types=1);

namespace App\Performance\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
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
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/performance', name: 'performance_index', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::PerformanceOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $nPlusOneOnly = $request->query->getBoolean('nplus1');
        $transactions = $this->transactionRepository->findForProject($project, $nPlusOneOnly);

        return $this->render('performance/index.html.twig', [
            'project' => $project,
            'transactions' => $transactions,
            'nPlusOneOnly' => $nPlusOneOnly,
        ]);
    }

    #[Route('/projects/{projectId}/performance/{id}', name: 'performance_show', requirements: ['projectId' => Requirement::UUID, 'id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        string $projectId,
        #[MapEntity(mapping: ['id' => 'uuid'])]
        PerfTransaction $transaction,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $project = $transaction->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectId) {
            throw $this->createNotFoundException();
        }
        $this->projectAccess->requireMembership($project, $user);

        return $this->render('performance/show.html.twig', [
            'project' => $project,
            'transaction' => $transaction,
        ]);
    }
}
