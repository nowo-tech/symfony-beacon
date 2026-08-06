<?php

declare(strict_types=1);

namespace App\Tests\Integration\Issues;

use App\Identity\Entity\User;
use App\Issues\Service\IssueAssigneeGuard;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IssueAssigneeGuardTest extends DatabaseWebTestCase
{
    public function testNullAssigneeIsAllowed(): void
    {
        [, , $project] = $this->bootWithDemoProject('assignee-guard-null@example.com');
        $guard = self::getContainer()->get(IssueAssigneeGuard::class);

        $guard->assertAssignable($project, null);
        $this->addToAssertionCount(1);
    }

    public function testMemberAssigneePasses(): void
    {
        [, $user, $project] = $this->bootWithDemoProject('assignee-guard-member@example.com');
        $guard = self::getContainer()->get(IssueAssigneeGuard::class);

        $guard->assertAssignable($project, $user);
        $this->addToAssertionCount(1);
    }

    public function testStrangerThrows(): void
    {
        [, , $project] = $this->bootWithDemoProject('assignee-guard-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('assignee-guard-stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $guard = self::getContainer()->get(IssueAssigneeGuard::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('assignee_not_member');
        $guard->assertAssignable($project, $stranger);
    }
}
