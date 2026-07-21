<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Tests\Shared\DatabaseWebTestCase;
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
            'name' => 'Platform',
            'description' => 'Platform team',
        ]);
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Platform');

        $group = $em->getRepository(UserGroup::class)->findOneBy(['slug' => 'platform']);
        self::assertInstanceOf(UserGroup::class, $group);

        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        $form = $crawler->selectButton('Add member')->form([
            'email' => 'group-user@example.com',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'group-user@example.com');
    }
}
