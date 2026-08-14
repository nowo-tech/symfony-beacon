<?php

declare(strict_types=1);

namespace App\Tests\Unit\Api\Read\Controller;

use App\Api\Read\Controller\ProjectReadApiController;
use App\Api\Read\Dto\ProjectIssuesListQuery;
use App\Identity\Service\UserActionRecorder;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Repository\IssueRepository;
use App\Issues\Repository\IssueSearchRepository;
use App\Issues\Service\IssueJsonNormalizer;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectReadToken;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectReadTokenRepository;
use App\Project\Repository\ProjectRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectReadTokenManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectReadApiControllerTest extends TestCase
{
    public function testAuthorizeRejectsMissingBearerAndBadToken(): void
    {
        $controller = $this->controller();

        $missing = $controller->listIssues(Request::create('/api/projects/x/issues'), '11111111-1111-4111-8111-111111111111');
        self::assertSame(Response::HTTP_UNAUTHORIZED, $missing->getStatusCode());

        $bad = $controller->listIssues(
            Request::create('/api/projects/x/issues', server: ['HTTP_AUTHORIZATION' => 'Bearer not-a-token']),
            '11111111-1111-4111-8111-111111111111',
        );
        self::assertSame(Response::HTTP_UNAUTHORIZED, $bad->getStatusCode());
    }

    public function testListAndShowHappyPathAndNotFound(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp')
            ->setTitle('Boom')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);

        $raw = 'brt_'.str_repeat('ab', 24);
        $token = new ProjectReadToken();
        $token->setProject($project);
        $token->setLabel('bot');
        $token->setPrefix(substr($raw, 0, 12));
        $token->setTokenHash(hash('sha256', $raw));

        $tokenRepo = $this->createStub(ProjectReadTokenRepository::class);
        $tokenRepo->method('findActiveByTokenHash')->willReturn($token);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn($project);

        $search = $this->createStub(IssueSearchRepository::class);
        $search->method('search')->willReturn([$issue]);

        $issues = $this->createStub(IssueRepository::class);
        $issues->method('findOneByProjectAndUuid')->willReturnCallback(
            static fn (Project $p, string $uuid): ?Issue => $uuid === $issue->getUuid() ? $issue : null,
        );

        $controller = $this->controller(
            tokenRepo: $tokenRepo,
            projects: $projects,
            search: $search,
            issues: $issues,
        );

        $authHeader = ['HTTP_AUTHORIZATION' => 'Bearer '.$raw];
        $list = $controller->listIssues(
            Request::create('/api/projects/'.$project->getUuid().'/issues', server: $authHeader),
            $project->getUuid(),
            new ProjectIssuesListQuery(limit: 10),
        );
        self::assertSame(Response::HTTP_OK, $list->getStatusCode());
        $listPayload = json_decode((string) $list->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(1, $listPayload['count']);
        self::assertSame('Boom', $listPayload['issues'][0]['title']);

        $show = $controller->showIssue(
            Request::create('/api/projects/'.$project->getUuid().'/issues/'.$issue->getUuid(), server: $authHeader),
            $project->getUuid(),
            $issue->getUuid(),
        );
        self::assertSame(Response::HTTP_OK, $show->getStatusCode());

        $missing = $controller->showIssue(
            Request::create('/api/projects/'.$project->getUuid().'/issues/22222222-2222-4222-8222-222222222222', server: $authHeader),
            $project->getUuid(),
            '22222222-2222-4222-8222-222222222222',
        );
        self::assertSame(Response::HTTP_NOT_FOUND, $missing->getStatusCode());
    }

    public function testForbiddenWhenTokenProjectDoesNotMatchPath(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $raw = 'brt_'.str_repeat('cd', 24);
        $token = new ProjectReadToken();
        $token->setProject($project);
        $token->setLabel('bot');
        $token->setPrefix(substr($raw, 0, 12));
        $token->setTokenHash(hash('sha256', $raw));

        $tokenRepo = $this->createStub(ProjectReadTokenRepository::class);
        $tokenRepo->method('findActiveByTokenHash')->willReturn($token);
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findOneBy')->willReturn($project);

        $controller = $this->controller(tokenRepo: $tokenRepo, projects: $projects);
        $response = $controller->listIssues(
            Request::create('/api/projects/x/issues', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$raw]),
            '11111111-1111-4111-8111-111111111111',
        );
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    private function controller(
        ?ProjectReadTokenRepository $tokenRepo = null,
        ?ProjectRepository $projects = null,
        ?IssueSearchRepository $search = null,
        ?IssueRepository $issues = null,
    ): ProjectReadApiController {
        $em = $this->createStub(EntityManagerInterface::class);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $manager = new ProjectReadTokenManager(
            $em,
            $tokenRepo ?? $this->createStub(ProjectReadTokenRepository::class),
            new ProjectAccessService(
                $this->createStub(ProjectMembershipRepository::class),
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            new UserActionRecorder($em, new RequestStack()),
        );

        return new ProjectReadApiController(
            $manager,
            $projects ?? $this->createStub(ProjectRepository::class),
            $issues ?? $this->createStub(IssueRepository::class),
            $search ?? $this->createStub(IssueSearchRepository::class),
            new IssueJsonNormalizer(),
        );
    }
}
