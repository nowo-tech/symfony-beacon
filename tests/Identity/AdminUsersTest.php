<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Tests\Shared\DatabaseWebTestCase;
use Symfony\Component\HttpFoundation\Request;

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
            'email' => 'newbie@example.com',
            'display_name' => 'Newbie',
            'password' => 'secret123',
            'role' => 'user',
            'enabled' => '1',
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

        $crawler = $client->request(Request::METHOD_GET, '/admin/users');
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
        self::assertSame(UserActionType::UserRoleChanged, $actions[array_key_last($actions)]->getAction());

        $client->request(Request::METHOD_GET, '/admin/users/'.$reloaded->getUuid().'/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Activity history');
        self::assertSelectorTextContains('body', 'User created');
        self::assertSelectorTextContains('body', 'User role changed');
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
