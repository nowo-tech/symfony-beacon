<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Service;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Project\Service\ProjectMembershipUiHelper;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProjectMembershipUiHelperTest extends TestCase
{
    public function testRoleChoicesUsesI18nKeys(): void
    {
        self::assertSame(
            [
                'project.members.role.member' => 'member',
                'project.members.role.admin' => 'admin',
            ],
            ProjectMembershipUiHelper::roleChoices([ProjectRole::Member, ProjectRole::Admin]),
        );
    }

    public function testLinkableGroupsSkipsLinkedAndNullIds(): void
    {
        $linked = $this->groupWithId(1, 'Linked');
        $fresh = $this->groupWithId(2, 'Fresh');
        $noId = new UserGroup();
        $noId->setName('NoId');

        $project = new Project();
        $access = new ProjectGroupAccess();
        $access->setUserGroup($linked);
        $access->setRole(ProjectRole::Member);
        $project->addGroupAccess($access);

        $result = ProjectMembershipUiHelper::linkableGroups($project, [$linked, $fresh, $noId]);
        self::assertSame([$fresh], $result);
    }

    public function testGroupChoicesForLinkingWithAndWithoutCounts(): void
    {
        $group = $this->groupWithId(5, 'Ops');
        $project = new Project();

        self::assertSame(
            ['Ops' => $group->getUuid()],
            ProjectMembershipUiHelper::groupChoicesForLinking($project, [$group]),
        );

        self::assertSame(
            ['Ops (3)' => $group->getUuid()],
            ProjectMembershipUiHelper::groupChoicesForLinking($project, [$group], [5 => 3]),
        );
    }

    public function testGroupChoicesSkipGroupsWhoseIdDisappearsDuringChoiceBuild(): void
    {
        $group = new class extends UserGroup {
            private int $calls = 0;

            public function getId(): ?int
            {
                ++$this->calls;

                return 1 === $this->calls ? 9 : null;
            }
        };
        $group->setName('Flaky');

        self::assertSame([], ProjectMembershipUiHelper::groupChoicesForLinking(new Project(), [$group], [9 => 1]));
    }

    public function testTransferOwnershipChoicesExcludesActor(): void
    {
        $actor = $this->userWithId(1, 'actor@example.com', 'Actor');
        $other = $this->userWithId(2, 'other@example.com', 'Other');

        $project = new Project();
        $m1 = new ProjectMembership();
        $m1->setUser($actor);
        $m1->setRole(ProjectRole::Owner);
        $project->addMembership($m1);

        $m2 = new ProjectMembership();
        $m2->setUser($other);
        $m2->setRole(ProjectRole::Admin);
        $project->addMembership($m2);

        $choices = ProjectMembershipUiHelper::transferOwnershipChoices($project, $actor);
        self::assertArrayHasKey('Other (other@example.com) - admin', $choices);
        self::assertSame($other->getUuid(), $choices['Other (other@example.com) - admin']);
        self::assertCount(1, $choices);
    }

    private function groupWithId(int $id, string $name): UserGroup
    {
        $group = new UserGroup();
        $group->setName($name);
        $prop = new ReflectionProperty(UserGroup::class, 'id');
        $prop->setValue($group, $id);

        return $group;
    }

    private function userWithId(int $id, string $email, string $displayName): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setDisplayName($displayName);
        $prop = new ReflectionProperty(User::class, 'id');
        $prop->setValue($user, $id);

        return $user;
    }
}
