<?php

declare(strict_types=1);

namespace App\Api\Read\Controller;

use App\Api\Read\Dto\ProjectIssuesListQuery;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\IssueJsonNormalizer;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectReadTokenManager;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Bearer-authenticated JSON read API for project issues (automation; not public boards).
 *
 * Auth is application-level (firewall {@code read_api} uses {@code security: false}).
 * IP rate limiting is applied by {@see \App\Api\Read\EventSubscriber\ReadApiRateLimitSubscriber}.
 */
#[AsController]
final readonly class ProjectReadApiController
{
    public function __construct(
        private ProjectReadTokenManager $tokenManager,
        private ProjectRepository $projectRepository,
        private IssueRepository $issueRepository,
        private IssueSearchRepository $issueSearchRepository,
        private IssueJsonNormalizer $issueJsonNormalizer,
    ) {
    }

    #[Route('/api/projects/{projectUuid}/issues', name: 'api_project_issues_list', requirements: ['projectUuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(path: '/api/projects/{projectUuid}/issues', operationId: 'readProjectIssues', summary: 'List project issues (read token)', security: [['BeaconReadToken' => []]], tags: ['Read API'])]
    public function listIssues(
        Request $request,
        string $projectUuid,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)]
        ProjectIssuesListQuery $query = new ProjectIssuesListQuery(),
    ): JsonResponse {
        $auth = $this->authorize($request, $projectUuid);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        [$project] = $auth;

        $status = null !== $query->status && '' !== $query->status
            ? IssueStatus::tryFrom($query->status)
            : null;
        $issues = $this->issueSearchRepository->search(
            project: $project,
            query: $query->q ?: null,
            level: $query->level ?: null,
            status: $status,
            environment: $query->environment ?: null,
            release: $query->release ?: null,
            limit: $query->limit,
        );

        return new JsonResponse([
            'project' => ['uuid' => $project->getUuid(), 'name' => $project->getName()],
            'limit' => $query->limit,
            'count' => \count($issues),
            'issues' => array_map($this->issueJsonNormalizer->normalize(...), $issues),
        ]);
    }

    #[Route('/api/projects/{projectUuid}/issues/{issueUuid}', name: 'api_project_issue_show', requirements: ['projectUuid' => Requirement::UUID, 'issueUuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(path: '/api/projects/{projectUuid}/issues/{issueUuid}', operationId: 'readProjectIssue', summary: 'Get one project issue (read token)', security: [['BeaconReadToken' => []]], tags: ['Read API'])]
    public function showIssue(Request $request, string $projectUuid, string $issueUuid): JsonResponse
    {
        $auth = $this->authorize($request, $projectUuid);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        [$project] = $auth;

        $issue = $this->issueRepository->findOneByProjectAndUuid($project, $issueUuid);
        if (!$issue instanceof Issue) {
            return new JsonResponse(['error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'project' => ['uuid' => $project->getUuid(), 'name' => $project->getName()],
            'issue' => $this->issueJsonNormalizer->normalize($issue),
        ]);
    }

    /**
     * @return array{0: Project, 1: ProjectReadToken}|JsonResponse
     */
    private function authorize(Request $request, string $projectUuid): array|JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->tokenManager->authenticate($m[1]);
        if (!$token instanceof ProjectReadToken) {
            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $project = $token->getProject();
        if (!$project instanceof Project || $project->getUuid() !== $projectUuid) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        $canonical = $this->projectRepository->findOneBy(['uuid' => $projectUuid]);
        if (!$canonical instanceof Project || $canonical->getId() !== $project->getId()) {
            return new JsonResponse(['error' => 'forbidden'], Response::HTTP_FORBIDDEN);
        }

        return [$project, $token];
    }
}
