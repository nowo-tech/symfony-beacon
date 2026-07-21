<?php

declare(strict_types=1);

namespace App\Project\Controller;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Repository\EventRepository;
use App\Issues\Repository\IssueRepository;
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
 * Read-only project panel for release-focused issue summaries and comparisons.
 */
#[IsGranted('ROLE_USER')]
final class ProjectReleaseHealthController extends AbstractController
{
    public function __construct(
        private readonly IssueRepository $issueRepository,
        private readonly EventRepository $eventRepository,
        private readonly ProjectAccessService $projectAccess,
    ) {
    }

    #[Route('/projects/{id}/releases', name: 'project_releases', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function index(
        #[MapEntity(mapping: ['id' => 'uuid'])]
        Project $project,
        Request $request,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $this->projectAccess->requireMembership($project, $user);

        $issueReleases = $this->issueRepository->findDistinctReleases($project);
        $eventReleases = $this->eventRepository->findDistinctReleaseVersions($project);
        $releases = array_values(array_unique([...$issueReleases, ...$eventReleases]));
        $newIssueCounts = $this->issueRepository->countNewIssuesByFirstReleaseMap($project);

        $selectedRelease = $this->normalizeReleaseQuery($request->query->getString('release'));
        if (null === $selectedRelease && [] !== $releases) {
            $selectedRelease = $releases[0];
        }

        $compareRelease = $this->normalizeReleaseQuery($request->query->getString('compare'));
        if ($compareRelease === $selectedRelease) {
            $compareRelease = null;
        }

        $selectedCount = null;
        $selectedIssues = [];
        if (null !== $selectedRelease) {
            $selectedCount = $newIssueCounts[$selectedRelease] ?? 0;
            $selectedIssues = $this->issueRepository->findLatestNewIssuesByFirstRelease($project, $selectedRelease);
        }

        $compareResult = null;
        if (null !== $selectedRelease && null !== $compareRelease) {
            $compareResult = $this->buildReleaseCompare($project, $selectedRelease, $compareRelease);
        }

        $releaseSummaries = [];
        foreach ($releases as $release) {
            $releaseSummaries[] = [
                'release' => $release,
                'new_issue_count' => $newIssueCounts[$release] ?? 0,
            ];
        }

        return $this->render('project/releases.html.twig', [
            'project' => $project,
            'releases' => $releases,
            'release_summaries' => $releaseSummaries,
            'selected_release' => $selectedRelease,
            'selected_count' => $selectedCount,
            'selected_issues' => $selectedIssues,
            'compare_release' => $compareRelease,
            'compare_result' => $compareResult,
            'environment_a' => trim($request->query->getString('environment')),
            'environment_b' => trim($request->query->getString('environment_compare')),
        ]);
    }

    /**
     * @return array{
     *     releaseA: string,
     *     releaseB: string,
     *     onlyA: list<Issue>,
     *     onlyB: list<Issue>,
     *     both: list<Issue>,
     *     onlyACount: int,
     *     onlyBCount: int,
     *     bothCount: int
     * }
     */
    private function buildReleaseCompare(Project $project, string $releaseA, string $releaseB): array
    {
        $setA = $this->issueRepository->findByRelease($project, $releaseA);
        $setB = $this->issueRepository->findByRelease($project, $releaseB);

        $byIdA = [];
        foreach ($setA as $issue) {
            $id = $issue->getId();
            if (null !== $id) {
                $byIdA[$id] = $issue;
            }
        }

        $byIdB = [];
        foreach ($setB as $issue) {
            $id = $issue->getId();
            if (null !== $id) {
                $byIdB[$id] = $issue;
            }
        }

        $onlyA = [];
        $both = [];
        foreach ($byIdA as $id => $issue) {
            if (isset($byIdB[$id])) {
                $both[] = $issue;
            } else {
                $onlyA[] = $issue;
            }
        }

        $onlyB = [];
        foreach ($byIdB as $id => $issue) {
            if (!isset($byIdA[$id])) {
                $onlyB[] = $issue;
            }
        }

        return [
            'releaseA' => $releaseA,
            'releaseB' => $releaseB,
            'onlyA' => \array_slice($onlyA, 0, 8),
            'onlyB' => \array_slice($onlyB, 0, 8),
            'both' => \array_slice($both, 0, 8),
            'onlyACount' => \count($onlyA),
            'onlyBCount' => \count($onlyB),
            'bothCount' => \count($both),
        ];
    }

    private function normalizeReleaseQuery(?string $value): ?string
    {
        return Issue::normalizeRelease($value);
    }
}
