<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectChildEntityGuard;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectChildEntityGuardTest extends TestCase
{
    public function testThrowsWhenEntityProjectMissing(): void
    {
        $guard = new ProjectChildEntityGuard($this->projectAccess(false));

        $this->expectException(NotFoundHttpException::class);
        $guard->requireManagedChild('proj-uuid', null, new User());
    }

    public function testThrowsWhenUuidMismatch(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');

        $guard = new ProjectChildEntityGuard($this->projectAccess(false));

        $this->expectException(NotFoundHttpException::class);
        $guard->requireManagedChild('other-uuid', $project, new User());
    }

    public function testRequiresPermissionAndReturnsProjectForAdmin(): void
    {
        $project = new Project();
        $project->setSlug('demo');
        $project->setName('Demo');
        $user = new User();
        $user->setEmail('admin@example.com');

        $guard = new ProjectChildEntityGuard($this->projectAccess(true));

        self::assertSame($project, $guard->requireManagedChild($project->getUuid(), $project, $user));
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
