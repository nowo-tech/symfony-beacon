<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Adapter;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupMembershipRepository;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\UserActionRecorder;
use App\Project\Adapter\ProjectMembershipAdminAdapter;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Project\Repository\ProjectMembershipRepository;
use App\Project\Repository\ProjectShareLinkRepository;
use App\Project\Service\ProjectAccessService;
use App\Project\Service\ProjectGroupAccessManager;
use App\Project\Service\ProjectMembershipManager;
use App\Project\Service\ProjectMembershipPolicy;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ProjectMembershipAdminAdapterTest extends TestCase
{
    public function testUnlinkMembershipAndGroupAccessDelegate(): void
    {
        $project = new Project()->setName('Acme')->setSlug('acme');
        new ReflectionProperty(Project::class, 'id')->setValue($project, 1);
        $actor = new User()->setEmail('admin@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($actor, 1);
        $memberUser = new User()->setEmail('dev@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($memberUser, 2);
        $membership = new ProjectMembership()->setProject($project)->setUser($memberUser)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectMembership::class, 'id')->setValue($membership, 9);
        $project->addMembership($membership);

        $group = new UserGroup()->setName('Eng')->setSlug('eng');
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, 3);
        $groupAccess = new ProjectGroupAccess()->setProject($project)->setUserGroup($group)->setRole(ProjectRole::Member);
        new ReflectionProperty(ProjectGroupAccess::class, 'id')->setValue($groupAccess, 4);
        $project->addGroupAccess($groupAccess);

        $removed = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });
        $em->method('flush');
        $em->method('persist');

        $memberships = $this->createStub(ProjectMembershipRepository::class);
        $memberships->method('findOneByProjectAndUser')->willReturn(
            new ProjectMembership()->setProject($project)->setUser($actor)->setRole(ProjectRole::Owner),
        );
        $memberships->method('countOwnersByProjectIds')->willReturn([1 => 1]);
        $groups = $this->createStub(ProjectGroupAccessRepository::class);
        $groups->method('findHighestGroupRoleForUser')->willReturn(null);
        $auth = $this->createStub(AuthorizationCheckerInterface::class);
        $auth->method('isGranted')->willReturn(true);
        $access = new ProjectAccessService(
            $memberships,
            $groups,
            $this->createStub(ProjectShareLinkRepository::class),
            $auth,
            new RequestStack(),
        );
        $policy = new ProjectMembershipPolicy(
            $memberships,
            $this->createStub(UserGroupMembershipRepository::class),
            $access,
            $auth,
        );
        $recorder = new UserActionRecorder($em, new RequestStack());
        $adapter = new ProjectMembershipAdminAdapter(
            new ProjectMembershipManager(
                $this->createStub(UserRepository::class),
                $memberships,
                $policy,
                $recorder,
                $em,
            ),
            new ProjectGroupAccessManager($groups, $policy, $recorder, $em),
        );

        $adapter->unlinkMembership($project, $actor, $membership);
        $adapter->unlinkGroupAccess($project, $actor, $groupAccess);

        self::assertContains($membership, $removed);
        self::assertContains($groupAccess, $removed);
    }
}
