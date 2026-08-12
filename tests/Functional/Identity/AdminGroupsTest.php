<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Enum\ProjectRole;
use App\Project\Repository\ProjectGroupAccessRepository;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminGroupsTest extends DatabaseWebTestCase
{
    public function testAdminCanCreateGroupAndAddMember(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('admin-groups@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $member = new User();
        $member->setEmail('group-user@example.com');
        $member->setDisplayName('Group User');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $em->persist($member);
        $em->flush();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('New group')->form([
            'admin_group[name]' => 'Platform',
            'admin_group[description]' => 'Platform team',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Platform');

        $group = $em->getRepository(UserGroup::class)->findOneBy(['slug' => 'platform']);
        self::assertInstanceOf(UserGroup::class, $group);
        self::assertNotNull($group->getCreatedAt());
        self::assertNotNull($group->getUpdatedAt());
        self::assertSame($admin->getId(), $group->getCreatedBy()?->getId());
        self::assertSame($admin->getId(), $group->getUpdatedBy()?->getId());

        $client->request(Request::METHOD_GET, '/admin/groups');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="audit-meta"]');
        self::assertSelectorTextContains('body', 'Test User');

        $client->request(Request::METHOD_GET, '/admin/groups', ['q' => 'Platform']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/admin/groups"] input[name="q"]');
        self::assertSelectorTextContains('body', 'Platform');

        $client->request(Request::METHOD_GET, '/admin/groups', ['q' => 'no-such-group-zzz']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'No groups yet');

        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        self::assertSelectorExists('[data-testid="audit-meta"]');
        $form = $crawler->selectButton('Add member')->form([
            'email' => 'group-user@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'group-user@example.com');
        self::assertSelectorExists('[data-testid="group-audit-entry"]');
        self::assertSelectorTextContains('#group-audit-timeline', 'Group member added');
        self::assertSelectorTextContains('#group-audit-timeline', 'Group created');

        $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid(), [
            'action' => 'group.member_added',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="group-audit-entry"]');
        self::assertSelectorTextContains('#group-audit-timeline', 'Group member added');
        self::assertSelectorTextNotContains('#group-audit-timeline', 'Group created');
    }

    public function testNonAdminCannotOpenGroupShow(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-groups-denied@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $group = new UserGroup();
        $group->setName('Secret');
        $group->setSlug('secret-group-denied');
        $em->persist($group);
        $em->flush();

        $this->login($client, $user);
        $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanUnlinkProjectFromGroup(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-group-unlink@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $group = new UserGroup();
        $group->setName('Ops');
        $group->setSlug('ops');
        $access = new ProjectGroupAccess();
        $access->setUserGroup($group);
        $access->setRole(ProjectRole::Member);
        $project->addGroupAccess($access);
        $em->persist($group);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acme');

        $form = $crawler->selectButton('Unlink')->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'This group is not linked to any project yet.');

        $em->clear();
        $remaining = self::getContainer()->get(ProjectGroupAccessRepository::class)->findByUserGroup(
            $em->getRepository(UserGroup::class)->find($group->getId()),
        );
        self::assertSame([], $remaining);
    }
}
