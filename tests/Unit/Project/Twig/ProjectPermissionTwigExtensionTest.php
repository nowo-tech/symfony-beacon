<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Twig;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Security\ProjectPermission;
use App\Project\Service\ProjectAccessService;
use App\Project\Twig\ProjectPermissionTwigExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectPermissionTwigExtensionTest extends TestCase
{
    private ProjectMembershipRepository&MockObject $membershipRepository;
    private AuthorizationCheckerInterface&MockObject $authorizationChecker;
    private Security&MockObject $security;
    private ProjectPermissionTwigExtension $extension;

    protected function setUp(): void
    {
        $this->membershipRepository = $this->createMock(ProjectMembershipRepository::class);
        $groupAccessRepository = $this->createMock(ProjectGroupAccessRepository::class);
        $groupAccessRepository->method('findHighestGroupRoleForUser')->willReturn(null);
        $shareLinkRepository = $this->createMock(ProjectShareLinkRepository::class);
        $this->authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $this->authorizationChecker->method('isGranted')->with('ROLE_ADMIN')->willReturn(false);
        $this->security = $this->createMock(Security::class);

        $projectAccess = new ProjectAccessService(
            $this->membershipRepository,
            $groupAccessRepository,
            $shareLinkRepository,
            $this->authorizationChecker,
            new RequestStack(),
        );
        $this->extension = new ProjectPermissionTwigExtension($projectAccess, $this->security);
    }

    public function testGrantsFalseWhenAnonymous(): void
    {
        $this->security->method('getUser')->willReturn(null);
        $project = new Project();

        self::assertFalse($this->extension->grants($project, ProjectPermission::VIEW));
        self::assertFalse($this->extension->canOpenSettings($project));
        self::assertNull($this->extension->access($project));
    }

    public function testGrantsUsesResolvedMembership(): void
    {
        $user = new User();
        $project = new Project();
        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole(ProjectRole::Member);
        $this->security->method('getUser')->willReturn($user);
        $this->membershipRepository->method('findOneByProjectAndUser')
            ->with($project, $user)
            ->willReturn($membership);

        self::assertTrue($this->extension->grants($project, ProjectPermission::ISSUES_TRIAGE));
        self::assertFalse($this->extension->grants($project, ProjectPermission::SETTINGS_MANAGE));
        self::assertFalse($this->extension->canOpenSettings($project));
    }

    public function testRepeatedGrantsReuseResolvedAccessWithoutExtraMembershipLookups(): void
    {
        $user = new User();
        $project = new Project();
        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole(ProjectRole::Member);

        $projectId = new ReflectionProperty(Project::class, 'id');
        $projectId->setValue($project, 7);
        $userId = new ReflectionProperty(User::class, 'id');
        $userId->setValue($user, 3);

        $this->security->method('getUser')->willReturn($user);
        $this->membershipRepository->expects(self::once())
            ->method('findOneByProjectAndUser')
            ->with($project, $user)
            ->willReturn($membership);

        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $groupAccessRepository = $this->createMock(ProjectGroupAccessRepository::class);
        $groupAccessRepository->expects(self::once())
            ->method('findHighestGroupRoleForUser')
            ->willReturn(null);
        $shareLinkRepository = $this->createMock(ProjectShareLinkRepository::class);
        $projectAccess = new ProjectAccessService(
            $this->membershipRepository,
            $groupAccessRepository,
            $shareLinkRepository,
            $this->authorizationChecker,
            $requestStack,
        );
        $extension = new ProjectPermissionTwigExtension($projectAccess, $this->security);

        self::assertTrue($extension->grants($project, ProjectPermission::ISSUES_TRIAGE));
        self::assertTrue($extension->grants($project, ProjectPermission::ISSUES_TRIAGE));
        self::assertTrue($extension->grants($project, ProjectPermission::VIEW));
        self::assertFalse($extension->canOpenSettings($project));
    }

    public function testAdminCanOpenSettings(): void
    {
        $user = new User();
        $project = new Project();
        $membership = new ProjectMembership();
        $membership->setUser($user);
        $membership->setRole(ProjectRole::Admin);
        $this->security->method('getUser')->willReturn($user);
        $this->membershipRepository->method('findOneByProjectAndUser')->willReturn($membership);

        self::assertTrue($this->extension->canOpenSettings($project));
        self::assertTrue($this->extension->grants($project, ProjectPermission::MEMBERS_MANAGE));
    }
}
