<?php

declare(strict_types=1);

namespace App\Analytics\Controller;

use App\Analytics\Dto\AnalyticsDayPoint;
use App\Analytics\Service\AnalyticsPeriodResolver;
use App\Analytics\Service\AnalyticsSeriesService;
use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Service\ProjectAccessService;
use App\Shared\Pagination\PagePagination;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Per-project analytics: period charts + daily table (errors / transactions / N+1).
 */
#[IsGranted('ROLE_USER')]
final class AnalyticsController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsPeriodResolver $periodResolver,
        private readonly AnalyticsSeriesService $seriesService,
        private readonly ProjectAccessService $projectAccess,
        private readonly UserActionRecorder $userActionRecorder,
    ) {
    }

    #[Route('/projects/{id}/analytics', name: 'analytics_show', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $this->userActionRecorder->recordAndFlush(UserActionType::AnalyticsOpened, $user, $user, [
            'project_uuid' => $project->getUuid(),
            'project_name' => $project->getName(),
        ]);

        $resolved = $this->periodResolver->resolve($request);
        if (!$resolved['valid'] && null !== $resolved['error']) {
            $this->addFlash('warning', $resolved['error']);
        }

        $environment = $request->query->getString('environment') ?: null;
        $release = $request->query->getString('release') ?: null;
        $level = $request->query->getString('level') ?: null;
        $filtered = $this->seriesService->hasFilters($environment, $release, $level);

        $seriesAsc = $this->seriesService->build(
            $project,
            $resolved['from'],
            $resolved['to'],
            $environment,
            $release,
            $level,
        );
        $hasVolume = array_any($seriesAsc, static fn (AnalyticsDayPoint $point): bool => $point->errorCount > 0
            || ($point->transactionCount ?? 0) > 0
            || ($point->nPlusOneCount ?? 0) > 0);

        $seriesDesc = array_reverse($seriesAsc);
        $total = \count($seriesDesc);
        $pagination = PagePagination::fromRequest($request, $total);
        $pageRows = \array_slice($seriesDesc, $pagination['offset'], $pagination['per_page']);

        $filterQuery = $this->periodResolver->queryParams($resolved, $environment, $release, $level);

        $chartPoints = array_map(static fn (AnalyticsDayPoint $p): array => $p->toChartArray(), $seriesAsc);

        return $this->render('analytics/show.html.twig', [
            'project' => $project,
            'stats' => $pageRows,
            'pagination' => $pagination,
            'period' => $resolved['period'],
            'from' => $resolved['from'],
            'to' => $resolved['to'],
            'environment' => $environment ?? '',
            'release' => $release ?? '',
            'level' => $level ?? '',
            'filtered' => $filtered,
            'has_volume' => $hasVolume,
            'filter_query' => $filterQuery,
            'chart_points' => $chartPoints,
            'presets' => ['7', '14', '30', '90'],
        ]);
    }
}
