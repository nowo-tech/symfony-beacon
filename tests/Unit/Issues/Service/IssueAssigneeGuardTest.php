<?php

declare(strict_types=1);

namespace App\Tests\Unit\Issues\Service;

use App\Identity\Entity\User;
use App\Issues\Service\IssueAssigneeGuard;
use App\Project\Entity\Project;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use InvalidArgumentException;

final class IssueAssigneeGuardTest extends TestCase
{
    public function testNullAssigneeIsAllowed(): void
    {
        $guard = new IssueAssigneeGuard($this->projectAccess(false));
        $guard->assertAssignable(new Project(), null);
        $this->addToAssertionCount(1);
    }

    public function testAdminAssigneeIsAllowed(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $user = new User();
        $user->setEmail('admin@example.com');

        $guard = new IssueAssigneeGuard($this->projectAccess(true));
        $guard->assertAssignable($project, $user);
        $this->addToAssertionCount(1);
    }

    public function testNonMemberThrows(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $user = new User();
        $user->setEmail('outsider@example.com');

        $guard = new IssueAssigneeGuard($this->projectAccess(false));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('assignee_not_member');
        $guard->assertAssignable($project, $user);
    }

    private function projectAccess(bool $isAdmin): ProjectAccessService
    {
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn($isAdmin);

        return new ProjectAccessService(
            $this->createStub(ProjectMembershipRepository::class),
            $this->createStub(ProjectGroupAccessRepository::class),
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
    }
}
