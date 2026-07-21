<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Service\ProjectMembershipManager;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectGroupAccessTest extends DatabaseWebTestCase
{
    public function testGroupMemberGainsProjectAccessWithoutDirectMembership(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-group@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $groupUser = new User();
        $groupUser->setEmail('via-group@example.com');
        $groupUser->setDisplayName('Via Group');
        $groupUser->setPassword($hasher->hashPassword($groupUser, 'secret'));

        $group = new UserGroup();
        $group->setName('Engineers');
        $group->setSlug('engineers');
        $gm = new UserGroupMembership();
        $gm->setUser($groupUser);
        $group->addMembership($gm);

        $access = new ProjectGroupAccess();
        $access->setUserGroup($group);
        $access->setRole(ProjectRole::Member);
        $project->addGroupAccess($access);

        $em->persist($groupUser);
        $em->persist($group);
        $em->flush();

        $this->login($client, $groupUser);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/dashboard');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', $project->getName());
        unset($owner);
    }

    public function testOwnerCanLinkGroupFromSettings(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-link-group@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $group = new UserGroup();
        $group->setName('Ops');
        $group->setSlug('ops');
        $em->persist($group);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/groups"]');

        $token = $crawler->filter('form[action$="/groups"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/groups', [
            '_token' => $token,
            'group' => $group->getUuid(),
            'role' => 'admin',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');

        $em->clear();
        $linked = $em->getRepository(ProjectGroupAccess::class)->findOneBy(['project' => $project->getId()]);
        self::assertInstanceOf(ProjectGroupAccess::class, $linked);
        self::assertSame(ProjectRole::Admin, $linked->getRole());
    }

    public function testProjectAdminCannotLinkGroupTheyDoNotBelongTo(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-group-gate@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = new User();
        $admin->setEmail('proj-admin@example.com');
        $admin->setDisplayName('Proj Admin');
        $admin->setPassword($hasher->hashPassword($admin, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($admin);
        $membership->setRole(ProjectRole::Admin);
        $project->addMembership($membership);

        $group = new UserGroup();
        $group->setName('Secret Ops');
        $group->setSlug('secret-ops');
        $em->persist($admin);
        $em->persist($group);
        $em->flush();

        $manager = self::getContainer()->get(ProjectMembershipManager::class);
        try {
            $manager->addGroup($project, $admin, $group, ProjectRole::Member);
            self::fail('Expected group_link_forbidden');
        } catch (RuntimeException $e) {
            self::assertSame('group_link_forbidden', $e->getMessage());
        }

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('option[value="'.$group->getUuid().'"]');
        self::assertNull($em->getRepository(ProjectGroupAccess::class)->findOneBy(['project' => $project->getId()]));
        unset($owner);
    }
}
