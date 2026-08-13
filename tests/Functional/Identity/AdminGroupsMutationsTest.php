<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Identity\Entity\UserGroupMembership;
use App\Setup\Demo\DashboardMenuDemoSeeder;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AdminGroupsMutationsTest extends DatabaseWebTestCase
{
    public function testAdminCanEditAndDeleteGroup(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('admin-group-edit@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $group = new UserGroup();
        $group->setName('Legacy');
        $group->setSlug('legacy');
        $em->persist($group);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid().'/edit');
        self::assertResponseIsSuccessful();

        $form = $crawler->selectButton('Save group')->form([
            'admin_group[name]' => 'Platform Team',
            'admin_group[description]' => 'Updated description',
        ]);
        $client->submit($form);
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Platform Team');

        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        $client->submit($crawler->filter('form[action$="/delete"]')->form());
        self::assertResponseRedirects('/admin/groups');

        $em->clear();
        self::assertNull($em->getRepository(UserGroup::class)->find($group->getId()));
    }

    public function testAdminCanRemoveMemberAndRejectUnknownEmail(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('admin-group-members@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('group-remove@example.com');
        $member->setDisplayName('Group Remove');
        $member->setPassword($hasher->hashPassword($member, 'secret'));

        $group = new UserGroup();
        $group->setName('Removable');
        $group->setSlug('removable');
        $membership = new UserGroupMembership();
        $membership->setUser($member);
        $group->addMembership($membership);

        $em->persist($member);
        $em->persist($group);
        $em->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $this->login($client, $admin);
        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());

        $addToken = $crawler->filter('form[action$="/members"] input[name="admin_group_member_add[_token]"]')->attr('value');
        $client->request(Request::METHOD_POST, '/admin/groups/'.$group->getUuid().'/members', [
            'admin_group_member_add' => [
                '_token' => $addToken,
                'email' => 'missing-user@example.com',
            ],
        ]);
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'No user exists with that email');

        $crawler = $client->request(Request::METHOD_GET, '/admin/groups/'.$group->getUuid());
        $removeForm = $crawler->filter('form[action$="/members/'.$member->getUuid().'/remove"]');
        $client->submit($removeForm->form());
        self::assertResponseRedirects('/admin/groups/'.$group->getUuid());
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'No members in this group yet');

        $em->clear();
        self::assertNull($em->getRepository(UserGroupMembership::class)->find($membership->getId()));
    }
}
