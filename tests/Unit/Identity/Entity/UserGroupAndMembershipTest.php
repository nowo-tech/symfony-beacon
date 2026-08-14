<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Entity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

final class UserGroupAndMembershipTest extends TestCase
{
    public function testGroupTrimsFieldsAndTracksMemberships(): void
    {
        $group = new UserGroup()
            ->setName('  Ops Team  ')
            ->setSlug('  OPS-TEAM  ')
            ->setDescription('  Helps  ');
        self::assertSame('Ops Team', $group->getName());
        self::assertSame('ops-team', $group->getSlug());
        self::assertSame('Helps', $group->getDescription());
        self::assertNull($group->setDescription('   ')->getDescription());

        $owner = new User()->setEmail('owner@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($owner, 1);
        $group->setCreatedBy($owner);
        $group->setUpdatedBy($owner);
        self::assertSame($owner, $group->getCreatedBy());
        self::assertSame($owner, $group->getUpdatedBy());
        $group->setCreatedBy(new stdClass());
        self::assertNull($group->getCreatedBy());

        $member = new User()->setEmail('member@example.com');
        new ReflectionProperty(User::class, 'id')->setValue($member, 2);
        $membership = new UserGroupMembership()->setUser($member);
        $group->addMembership($membership);
        self::assertSame($group, $membership->getUserGroup());
        self::assertTrue($group->hasUser($member));
        self::assertFalse($group->hasUser($owner));

        $group->removeMembership($membership);
        self::assertFalse($group->getMemberships()->contains($membership));
        self::assertInstanceOf(DateTimeImmutable::class, $membership->getCreatedAt());
    }
}
