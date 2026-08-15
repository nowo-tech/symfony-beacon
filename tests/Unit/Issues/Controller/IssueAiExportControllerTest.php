<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Controller;

use App\Identity\Entity\User;
use App\Issues\Controller\IssueAiExportController;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Issues\Enum\IssueLevel;
use App\Issues\Enum\IssueStatus;
use App\Issues\Export\AiIssueExportFormatter;
use App\Issues\Repository\EventRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class IssueAiExportControllerTest extends TestCase
{
    public function testExportMarkdownAndJson(): void
    {
        [$controller, $project, $issue] = $this->controller();

        $md = $controller->exportAiMarkdown(Request::create('/export'), $project->getUuid(), $issue);
        self::assertSame(Response::HTTP_OK, $md->getStatusCode());
        self::assertStringContainsString('text/markdown', (string) $md->headers->get('Content-Type'));
        self::assertStringContainsString('Something broke', (string) $md->getContent());

        $json = $controller->exportAiJson(Request::create('/export'), $project->getUuid(), $issue);
        self::assertSame(Response::HTTP_OK, $json->getStatusCode());
        self::assertStringContainsString('application/json', (string) $json->headers->get('Content-Type'));
        $payload = json_decode((string) $json->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame(AiIssueExportFormatter::FORMAT, $payload['format']);
    }

    public function testRejectsWrongProjectUuid(): void
    {
        [$controller, , $issue] = $this->controller();
        $this->expectException(NotFoundHttpException::class);
        $controller->exportAiMarkdown(
            Request::create('/export'),
            '11111111-1111-4111-8111-111111111111',
            $issue,
        );
    }

    /**
     * @return array{0: IssueAiExportController, 1: Project, 2: Issue}
     */
    private function controller(): array
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $issue = new Issue()
            ->setProject($project)
            ->setFingerprint('fp-1')
            ->setTitle('Something broke')
            ->setLevel(IssueLevel::Error)
            ->setStatus(IssueStatus::Unresolved);
        new ReflectionProperty(Issue::class, 'id')->setValue($issue, 3);

        $event = new Event()
            ->setIssue($issue)
            ->setEventId('evt-1')
            ->setEnvironment('prod')
            ->setPayload([]);
        new ReflectionProperty(Event::class, 'id')->setValue($event, 9);

        $events = $this->createStub(EventRepository::class);
        $events->method('findLatestForIssue')->willReturn([$event]);
        $events->method('findOneByProjectAndEventId')->willReturn($event);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $access = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $controller = new IssueAiExportController(new AiIssueExportFormatter(), $events, $access);
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturn('https://beacon.test/issues/1');
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $urls);
        $controller->setContainer($container);

        return [$controller, $project, $issue];
    }
}
