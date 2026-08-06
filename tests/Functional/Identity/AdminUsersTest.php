<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Project\Repository\ProjectMembershipRepository;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminUsersTest extends DatabaseWebTestCase
{
    public function testAdminUsersListsStatusAndPresence(): void
    {
        [$client, $user] = $this->bootWithDemoProject('admin-users@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setDisplayName('Admin Users');
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Users');
        self::assertSelectorTextContains('table', 'Enabled');
        self::assertSelectorExists('table .badge');
    }

    public function testAdminUsersSearchFiltersByEmailOrName(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('admin-users-search@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setDisplayName('Alpha Admin');
        $em = self::getContainer()->get('doctrine')->getManager();

        $other = new User();
        $other->setEmail('bravo-user@example.com');
        $other->setDisplayName('Bravo User');
        $other->setPassword('unused');
        $em->persist($other);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $admin);

        $client->request(Request::METHOD_GET, '/admin/users', ['q' => 'bravo']);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/admin/users"] input[name="q"]');
        self::assertSelectorTextContains('table', 'bravo-user@example.com');
        self::assertSelectorTextNotContains('table', 'admin-users-search@example.com');

        $client->request(Request::METHOD_GET, '/admin/users', ['q' => 'Alpha']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'admin-users-search@example.com');
        self::assertSelectorTextNotContains('table', 'bravo-user@example.com');
    }

    public function testAdminCanDisableAnotherUser(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('toggle-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get('doctrine')->getManager();

        $target = new User();
        $target->setEmail('toggle-target@example.com');
        $target->setDisplayName('Target');
        $target->setPassword('unused');
        $em->persist($target);
        $em->flush();

        $this->login($client, $admin);

        $crawler = $client->request(Request::METHOD_GET, '/admin/users');
        self::assertResponseIsSuccessful();

        $form = $crawler->filter('form[action$="/admin/users/'.$target->getUuid().'/toggle-enabled"]')->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/users');
        $client->followRedirect();

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($target->getId());
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isEnabled());
    }

    public function testAdminCanCreateUserAndChangeRole(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('create-admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get('doctrine')->getManager()->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $admin);

        $crawler = $client->request(Request::METHOD_GET, '/admin/users/new');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Add user')->form([
            'admin_user[email]' => 'newbie@example.com',
            'admin_user[displayName]' => 'Newbie',
            'admin_user[password]' => 'secret123',
            'admin_user[role]' => 'user',
            'admin_user[enabled]' => '1',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/users');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'newbie@example.com');

        $em = self::getContainer()->get('doctrine')->getManager();
        $created = $em->getRepository(User::class)->findOneBy(['email' => 'newbie@example.com']);
        self::assertInstanceOf(User::class, $created);
        self::assertNotContains('ROLE_ADMIN', $created->getRoles());
        self::assertTrue($created->isEnabled());
        self::assertNotNull($created->getCreatedAt());
        self::assertNotNull($created->getUpdatedAt());
        self::assertSame($admin->getId(), $created->getCreatedBy()?->getId());
        self::assertSame($admin->getId(), $created->getUpdatedBy()?->getId());

        $crawler = $client->request(Request::METHOD_GET, '/admin/users');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="audit-meta"]');
        self::assertSelectorTextContains('table', 'Test User');

        $form = $crawler->filter('form[action$="/admin/users/'.$created->getUuid().'/role"]')->form([
            'role' => 'admin',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/users');

        $em->clear();
        $reloaded = $em->getRepository(User::class)->find($created->getId());
        self::assertContains('ROLE_ADMIN', $reloaded?->getRoles() ?? []);

        $actions = $em->getRepository(UserAction::class)->findBy(
            ['subjectUser' => $reloaded],
            ['id' => 'ASC'],
        );
        self::assertGreaterThanOrEqual(2, \count($actions));
        self::assertSame(UserActionType::UserCreated, $actions[0]->getAction());
        self::assertSame(UserActionType::UserRoleChanged, array_last($actions)->getAction());

        $client->request(Request::METHOD_GET, '/admin/users/'.$reloaded->getUuid().'/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Activity history');
        self::assertSelectorTextContains('body', 'User created');
        self::assertSelectorTextContains('body', 'User role changed');
        self::assertSelectorExists('[data-testid="user-audit-entry"]');

        $client->request(Request::METHOD_GET, '/admin/users/'.$reloaded->getUuid().'/activity', [
            'action' => 'user.role_changed',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#user-audit-timeline', 'User role changed');
        self::assertSelectorTextNotContains('#user-audit-timeline', 'User created');
    }

    public function testAdminCanRemoveUserFromProjectAndBlocksLastOwner(): void
    {
        [$client, $admin, $project] = $this->bootWithDemoProject('admin-project-unlink@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('project-member@example.com');
        $member->setDisplayName('Project Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);

        $crawler = $client->request(Request::METHOD_GET, '/admin/users/'.$member->getUuid().'/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Acme');

        $form = $crawler->selectButton('Remove from project')->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/users/'.$member->getUuid().'/activity');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No direct project memberships.');

        $em->clear();
        $gone = self::getContainer()->get(ProjectMembershipRepository::class)->findOneByProjectAndUser(
            $em->getRepository(Project::class)->find($project->getId()),
            $em->getRepository(User::class)->find($member->getId()),
        );
        self::assertNull($gone);

        $crawler = $client->request(Request::METHOD_GET, '/admin/users/'.$admin->getUuid().'/activity');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Remove from project')->form();
        $client->submit($form);
        self::assertResponseRedirects('/admin/users/'.$admin->getUuid().'/activity');
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'The project must keep at least one owner.');

        $em->clear();
        $stillOwner = self::getContainer()->get(ProjectMembershipRepository::class)->findOneByProjectAndUser(
            $em->getRepository(Project::class)->find($project->getId()),
            $em->getRepository(User::class)->find($admin->getId()),
        );
        self::assertInstanceOf(ProjectMembership::class, $stillOwner);
        self::assertSame(ProjectRole::Owner, $stillOwner->getRole());
    }

    public function testProjectPersistSetsAuditTimestamps(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('audit-project@example.com');
        self::assertNotNull($project->getCreatedAt());
        self::assertNotNull($project->getUpdatedAt());

        $this->login($client, $user);
        $em = self::getContainer()->get('doctrine')->getManager();
        $project->setDescription('Audited update');
        $em->flush();

        $em->refresh($project);
        self::assertSame($user->getId(), $project->getUpdatedBy()?->getId());
    }
}
