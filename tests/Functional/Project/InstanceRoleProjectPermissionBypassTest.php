<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\InstanceRole;
use App\Identity\Entity\User;
use App\Identity\Service\InstanceRbacSeeder;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Ensures InstanceRole ROLE_PROJECT_* cannot bypass per-project membership on
 * {@see \App\Project\Security\ProjectPermissionVoter}-gated routes.
 */
final class InstanceRoleProjectPermissionBypassTest extends DatabaseWebTestCase
{
    public function testInstanceProjectOwnerWithoutMembershipCannotDelete(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner-of-project@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(InstanceRbacSeeder::class)->seedIfEmpty();
        $em->clear();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $outsider = new User();
        $outsider->setEmail('instance-owner-no-membership@example.com');
        $outsider->setDisplayName('Instance Owner');
        $outsider->setPassword($hasher->hashPassword($outsider, 'secret'));

        $role = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_PROJECT_OWNER']);
        self::assertInstanceOf(InstanceRole::class, $role);
        $outsider->addInstanceRole($role);

        $em->persist($outsider);
        $em->flush();

        $this->login($client, $outsider);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/danger');
        self::assertResponseStatusCodeSame(403);
    }

    public function testInstanceProjectAdminWithoutMembershipCannotManageApiKeys(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('keys-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(InstanceRbacSeeder::class)->seedIfEmpty();
        $em->clear();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $outsider = new User();
        $outsider->setEmail('instance-admin-no-membership@example.com');
        $outsider->setDisplayName('Instance Admin');
        $outsider->setPassword($hasher->hashPassword($outsider, 'secret'));

        $role = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_PROJECT_ADMIN']);
        self::assertInstanceOf(InstanceRole::class, $role);
        $outsider->addInstanceRole($role);

        $em->persist($outsider);
        $em->flush();

        $this->login($client, $outsider);
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/keys');
        self::assertResponseStatusCodeSame(403);
    }

    public function testMemberWithInstanceViewerStillNeedsMembership(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('member-host@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::getContainer()->get(InstanceRbacSeeder::class)->seedIfEmpty();
        $em->clear();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('member-with-instance-viewer@example.com');
        $member->setDisplayName('Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $role = $em->getRepository(InstanceRole::class)->findOneBy(['code' => 'ROLE_PROJECT_VIEWER']);
        self::assertInstanceOf(InstanceRole::class, $role);
        $member->addInstanceRole($role);

        // Re-load project after clear()
        $project = $em->getRepository(Project::class)->findOneBy(['uuid' => $project->getUuid()]);
        self::assertInstanceOf(Project::class, $project);
        $owner = $em->getRepository(User::class)->findOneBy(['email' => 'member-host@example.com']);
        self::assertInstanceOf(User::class, $owner);

        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $em->persist($member);
        $em->flush();

        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/danger');
        self::assertResponseStatusCodeSame(403);

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/danger');
        self::assertResponseIsSuccessful();
        self::assertInstanceOf(Project::class, $em->getRepository(Project::class)->find($project->getId()));
    }
}
