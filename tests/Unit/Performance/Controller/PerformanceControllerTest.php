<?php

declare(strict_types=1);

namespace App\Tests\Unit\Performance\Controller;

use App\Identity\Entity\User;
use App\Identity\Service\UserActionRecorder;
use App\Performance\Controller\PerformanceController;
use App\Performance\Entity\PerfTransaction;
use App\Performance\Repository\PerfTransactionRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Tests\Support\ProjectAccessServiceFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class PerformanceControllerTest extends TestCase
{
    public function testIndexRendersTransactionsAndRecordsOpen(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        new ReflectionProperty(Project::class, 'uuid')->setValue($project, 'aaaaaaaa-aaaa-7aaa-8aaa-aaaaaaaaaaaa');
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $txs = $this->createStub(PerfTransactionRepository::class);
        $txs->method('countForProject')->willReturn(0);
        $txs->method('findPageForProject')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $controller = new PerformanceController(
            $txs,
            $this->access($memberships),
            new UserActionRecorder($em, new RequestStack()),
        );
        $seen = [];
        $this->boot($controller, $user, $seen);

        self::assertSame('ok', $controller->index($project, Request::create('/projects/x/performance'))->getContent());
        self::assertSame([], $seen['performance/index.html.twig']['transactions']);
        self::assertFalse($seen['performance/index.html.twig']['nPlusOneOnly']);
    }

    public function testShowRendersMatchingTransaction(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $tx = new PerfTransaction();
        $tx->setProject($project);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $repo = $this->createMock(PerfTransactionRepository::class);
        $repo->expects(self::once())->method('hydrateSpans')->with($tx);

        $controller = new PerformanceController(
            $repo,
            $this->access($memberships),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );
        $seen = [];
        $this->boot($controller, $user, $seen);

        self::assertSame('ok', $controller->show($project, $tx)->getContent());
        self::assertSame($tx, $seen['performance/show.html.twig']['transaction']);
    }

    public function testShowRejectsTransactionFromOtherProject(): void
    {
        $user = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, 1);
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 5);
        $other = new Project()->setName('Other')->setSlug('other');
        new ReflectionProperty(Project::class, 'id')->setValue($other, 6);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $tx = new PerfTransaction();
        $tx->setProject($other);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);

        $controller = new PerformanceController(
            $this->createStub(PerfTransactionRepository::class),
            $this->access($memberships),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );
        $seen = [];
        $this->boot($controller, $user, $seen);

        $this->expectException(NotFoundHttpException::class);
        $controller->show($project, $tx);
    }

    private function access(ProjectMembershipRepository $memberships): ProjectAccessService
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        return ProjectAccessServiceFactory::create(
            $memberships,
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
    }

    /**
     * @param array<string, array<string, mixed>> $seen
     */
    private function boot(object $controller, User $user, array &$seen): void
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static function (string $template, array $context) use (&$seen): string {
                $seen[$template] = $context;

                return 'ok';
            },
        );
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('twig', $twig);
        $controller->setContainer($container);
    }
}
