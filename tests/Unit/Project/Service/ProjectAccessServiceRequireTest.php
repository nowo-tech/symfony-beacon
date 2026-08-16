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
use App\Tests\Support\ProjectAccessServiceFactory;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectAccessServiceRequireTest extends TestCase
{
    public function testGetMembershipAliasAndRequireHelpers(): void
    {
        $project = $this->project(1);
        $user = $this->user(2);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Admin);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        self::assertSame($membership, $service->getMembership($project, $user));
        self::assertSame(ProjectRole::Admin, $service->requireMembership($project, $user)->role);
        self::assertSame(ProjectRole::Admin, $service->requireRole($project, $user, ProjectRole::Member)->role);
        self::assertSame(ProjectRole::Admin, $service->requireAnyPermission($project, $user, ProjectPermission::ISSUES_TRIAGE)->role);
        self::assertSame(ProjectRole::Admin, $service->requireSettingsSurface($project, $user)->role);

        $this->expectException(AccessDeniedHttpException::class);
        $service->requirePrimaryOwner($project, $user);
    }

    public function testRequireRoleRejectsInsufficientRank(): void
    {
        $project = $this->project(3);
        $user = $this->user(4);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->requireRole($project, $user, ProjectRole::Owner);
    }

    public function testRequireAnyPermissionRejectsEmptyPermissionList(): void
    {
        $project = $this->project(30);
        $user = $this->user(40);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Admin);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $service->requireAnyPermission($project, $user);
    }

    public function testResolveAccessDropsInactiveDirectMembership(): void
    {
        $project = $this->project(31);
        $user = $this->user(41);
        $membership = new ProjectMembership()->setProject($project)->setUser($user)->setRole(ProjectRole::Member)->setActive(false);
        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn($membership);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(false);
        $service = ProjectAccessServiceFactory::create(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );

        self::assertNull($service->resolveAccess($project, $user));
    }

    public function testShareGrantHelpersAndIssueRead(): void
    {
        $project = $this->project(5);
        $user = $this->user(9);
        $link = new ProjectShareLink()->setProject($project);
        $stack = new RequestStack();
        $request = Request::create('/');
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(ProjectAccessService::SHARE_ACCESS_SESSION_KEY, [
            $project->getUuid() => [
                'expires' => time() + 3600,
                'issue' => 'issue-uuid-1',
                'share' => $link->getUuid(),
            ],
        ]);
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
        $service = ProjectAccessServiceFactory::create($memberships, $groups, $shareLinks, $auth, $stack);

        self::assertTrue($service->hasActiveShareGrant($project));
        self::assertFalse($service->hasProjectWideShareGrant($project));
        self::assertTrue($service->hasShareGrantForIssue($project, 'issue-uuid-1'));
        self::assertFalse($service->hasShareGrantForIssue($project, 'other'));
        self::assertSame(ProjectRole::Viewer, $service->requireIssueRead($project, $user, 'issue-uuid-1')->role);

        $this->expectException(AccessDeniedHttpException::class);
        $service->requireIssueRead($project, $user, 'missing');
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
