<?php

declare(strict_types=1);

namespace App\Api\Read\Controller;

use App\Issues\Entity\Issue;
use App\Issues\Repository\IssueRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectReadTokenManager;
use App\Issues\Enum\IssueStatus;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Bearer-authenticated JSON read API for project issues (automation; not public boards).
 */
#[AsController]
final readonly class ProjectReadApiController
{
    public function __construct(
        private ProjectReadTokenManager $tokenManager,
        private ProjectRepository $projectRepository,
        private IssueRepository $issueRepository,
        private IssueSearchRepository $issueSearchRepository,
    ) {
    }

    #[Route('/api/projects/{projectUuid}/issues', name: 'api_project_issues_list', requirements: ['projectUuid' => Requirement::UUID], methods: ['GET'])]
    #[OA\Get(path: '/api/projects/{projectUuid}/issues', operationId: 'readProjectIssues', summary: 'List project issues (read token)', security: [['BeaconReadToken' => []]], tags: ['Read API'])]
    public function listIssues(Request $request, string $projectUuid): JsonResponse
    {
        $auth = $this->authorize($request, $projectUuid);
        if ($auth instanceof JsonResponse) {
            return $auth;
        }
        [$project] = $auth;

        $limit = max(1, min(1000, $request->query->getInt('limit', 100)));
        $statusRaw = $request->query->getString('status');
        $status = '' !== $statusRaw ? IssueStatus::tryFrom($statusRaw) : null;
        $issues = $this->issueSearchRepository->search(
            project: $project,
            query: $request->query->getString('q') ?: null,
            level: $request->query->getString('level') ?: null,
            status: $status,
            environment: $request->query->getString('environment') ?: null,
            release: $request->query->getString('release') ?: null,
            limit: $limit,
        );

        return new JsonResponse([
            'project' => ['uuid' => $project->getUuid(), 'name' => $project->getName()],
            'limit' => $limit,
            'count' => \count($issues),
            'issues' => array_map($this->issueToArray(...), $issues),
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
            'issue' => $this->issueToArray($issue),
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

    /**
     * @return array<string, mixed>
     */
    private function issueToArray(Issue $issue): array
    {
        return [
            'uuid' => $issue->getUuid(),
            'title' => $issue->getTitle(),
            'level' => $issue->getLevel(),
            'status' => $issue->getStatus()->value,
            'priority' => $issue->getPriority()->value,
            'culprit' => $issue->getCulprit(),
            'event_count' => $issue->getEventCount(),
            'first_seen' => $issue->getFirstSeen()->format(\DATE_ATOM),
            'last_seen' => $issue->getLastSeen()->format(\DATE_ATOM),
            'first_release' => $issue->getFirstRelease(),
            'last_release' => $issue->getLastRelease(),
            'last_environment' => $issue->getLastEnvironment(),
            'assignee_email' => $issue->getAssignee()?->getEmail(),
            'duplicate_of_uuid' => $issue->getDuplicateOf()?->getUuid(),
        ];
    }
}
