<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Repository\UserGroupRepository;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectRepository;
use App\Project\Service\ProjectMembershipFormSupport;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProjectMembershipFormSupportTest extends TestCase
{
    public function testGroupAndTransferChoicesHydrateThenDelegate(): void
    {
        $hydrated = false;
        $projectRepo = $this->createStub(ProjectRepository::class);
        $projectRepo->method('hydrateAccessGraph')->willReturnCallback(static function () use (&$hydrated): void {
            $hydrated = true;
        });
        $groupRepo = $this->createStub(UserGroupRepository::class);

        $project = new Project();
        $linked = $this->group(1, 'Linked');
        $free = $this->group(2, 'Free');
        $project->addGroupAccess(new ProjectGroupAccess()->setUserGroup($linked)->setRole(ProjectRole::Member));
        $groupRepo->method('findAllOrdered')->willReturn([$linked, $free]);

        $support = new ProjectMembershipFormSupport($projectRepo, $groupRepo);
        $choices = $support->groupChoicesForLinking($project, [2 => 3]);
        self::assertTrue($hydrated);
        self::assertSame([$free->getName().' (3)' => $free->getUuid()], $choices);

        $owner = $this->user(10, 'owner@example.com', 'Owner');
        $member = $this->user(11, 'm@example.com', 'Member');
        $project->addMembership(new ProjectMembership()->setUser($owner)->setRole(ProjectRole::Owner));
        $project->addMembership(new ProjectMembership()->setUser($member)->setRole(ProjectRole::Member));
        $transfer = $support->transferOwnershipChoices($project, $owner);
        self::assertSame(
            ['Member (m@example.com) - member' => $member->getUuid()],
            $transfer,
        );
    }

    private function group(int $id, string $name): UserGroup
    {
        $group = new UserGroup()->setName($name)->setSlug(strtolower($name));
        new ReflectionProperty(UserGroup::class, 'id')->setValue($group, $id);

        return $group;
    }

    private function user(int $id, string $email, string $display): User
    {
        $user = new User()->setEmail($email)->setDisplayName($display);
        new ReflectionProperty(User::class, 'id')->setValue($user, $id);

        return $user;
    }
}
