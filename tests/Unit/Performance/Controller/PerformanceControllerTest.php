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
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class PerformanceControllerTest extends TestCase
{
    public function testShowRejectsTransactionFromOtherProject(): void
    {
        $user = (new User())->setEmail('dev@example.com');
        (new ReflectionProperty(User::class, 'id'))->setValue($user, 1);
        $project = (new Project())->setName('Acme')->setSlug('acme');
        (new ReflectionProperty(Project::class, 'id'))->setValue($project, 5);
        $other = (new Project())->setName('Other')->setSlug('other');
        (new ReflectionProperty(Project::class, 'id'))->setValue($other, 6);
        $membership = (new ProjectMembership())->setProject($project)->setUser($user)->setRole(ProjectRole::Member);

        $tx = new PerfTransaction();
        $tx->setProject($other);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);

        $controller = new PerformanceController(
            $this->createStub(PerfTransactionRepository::class),
            new ProjectAccessService(
                $memberships,
                $this->createStub(ProjectGroupAccessRepository::class),
                $this->createStub(ProjectShareLinkRepository::class),
                $auth,
                new RequestStack(),
            ),
            new UserActionRecorder($this->createStub(EntityManagerInterface::class), new RequestStack()),
        );

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $controller->setContainer($container);

        $this->expectException(NotFoundHttpException::class);
        $controller->show($project, $tx);
    }
}
