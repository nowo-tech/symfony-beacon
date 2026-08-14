<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Entity\ProjectShareLink;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectAccessServiceAdminShareTest extends TestCase
{
    public function testRoleAdminResolvesAsOwnerUnlessViewAsMember(): void
    {
        $project = $this->project(1);
        $user = $this->user(2);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);

        $stack = new RequestStack();
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $stack->push($request);
        $service = new ProjectAccessService(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            $stack,
        );

        self::assertSame(ProjectRole::Owner, $service->resolveAccess($project, $user)?->role);
        self::assertFalse($service->isViewAsMemberActive());
        self::assertSame(ProjectRole::Owner, $service->requirePrimaryOwner($project, $user)->role);

        $session->set(ProjectAccessService::VIEW_AS_MEMBER_SESSION_KEY, true);
        $request2 = Request::create('/');
        $request2->setSession($session);
        $stack->pop();
        $stack->push($request2);
        self::assertTrue($service->isViewAsMemberActive());
        self::assertSame(ProjectRole::Member, $service->resolveAccess($project, $user)?->role);

        $this->expectException(AccessDeniedHttpException::class);
        $service->requirePrimaryOwner($project, $user);
    }

    public function testGrantShareAccessAndProjectWideGrant(): void
    {
        $project = $this->project(3);
        $user = $this->user(4);
        $link = new ProjectShareLink()->setProject($project);
        $stack = new RequestStack();
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request->setSession($session);
        $stack->push($request);

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(null);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $shareLinks = $this->createStub(ProjectShareLinkRepository::class);
        $shareLinks->method('findOneByUuid')->willReturn($link);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = new ProjectAccessService($memberships, $groups, $shareLinks, $auth, $stack);

        $service->grantShareAccess($project, null, time() + 3600, $link->getUuid());
        self::assertTrue($service->hasActiveShareGrant($project));
        self::assertTrue($service->hasProjectWideShareGrant($project));
        self::assertSame(ProjectRole::Viewer, $service->resolveAccess($project, $user)?->role);
    }

    public function testRequireTriageAndPermission(): void
    {
        $project = $this->project(5);
        $user = $this->user(6);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Viewer);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = new ProjectAccessService(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        try {
            $service->requireTriage($project, $user);
            self::fail('expected');
        } catch (AccessDeniedHttpException) {
            self::assertTrue(true);
        }

        $this->expectException(AccessDeniedHttpException::class);
        $service->requirePermission($project, $user, ProjectPermission::SETTINGS_MANAGE);
    }

    private function project(int $id): Project
    {
        $project = new Project()->setName('P')->setSlug('p'.$id);
        new ReflectionProperty(Project::class, 'id')->setValue($project, $id);

        return $project;
    }

    private function user(int $id): User
    {
        $user = new User()->setEmail('u'.$id.'@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
