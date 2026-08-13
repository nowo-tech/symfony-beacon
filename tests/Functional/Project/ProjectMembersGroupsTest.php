<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\User;
use App\Identity\Entity\UserGroup;
use App\Project\Entity\ProjectGroupAccess;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectMembersGroupsTest extends DatabaseWebTestCase
{
    public function testOwnerCanChangeGroupRoleAndRemoveGroupLink(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-group-role@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $group = new UserGroup();
        $group->setName('Platform');
        $group->setSlug('platform');
        $access = new ProjectGroupAccess();
        $access->setUserGroup($group);
        $access->setRole(ProjectRole::Member);
        $project->addGroupAccess($access);
        $em->persist($group);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();

        $roleForm = $crawler->filter('form[action$="/groups/'.$access->getUuid().'/role"]');
        self::assertGreaterThan(0, $roleForm->count());
        $token = $roleForm->filter('input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/groups/'.$access->getUuid().'/role', [
            '_token' => $token,
            'role' => 'admin',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');

        $em->clear();
        $reloaded = $em->getRepository(ProjectGroupAccess::class)->find($access->getId());
        self::assertInstanceOf(ProjectGroupAccess::class, $reloaded);
        self::assertSame(ProjectRole::Admin, $reloaded->getRole());

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $removeForm = $crawler->filter('form[action$="/groups/'.$access->getUuid().'/remove"]');
        $token = $removeForm->filter('input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/groups/'.$access->getUuid().'/remove', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');

        $em->clear();
        self::assertNull($em->getRepository(ProjectGroupAccess::class)->find($access->getId()));
    }

    public function testAddMemberShowsErrorForUnknownEmail(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-unknown-member@example.com');
        $this->login($client, $owner);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $token = $crawler->filter('form[action$="/members"] input[name="project_member_add[_token]"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members', [
            'project_member_add' => [
                '_token' => $token,
                'email' => 'nobody@example.com',
                'role' => 'member',
            ],
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'No user exists with that email');
    }

    public function testChangeRoleRejectsInvalidRoleValue(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-invalid-role@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('invalid-role@example.com');
        $member->setDisplayName('Invalid Role');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $project->addMembership(new ProjectMembership()->setUser($member)->setRole(ProjectRole::Member));
        $em->persist($member);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        $roleForm = $crawler->filter('form[action$="/members/'.$member->getUuid().'/role"]');
        $token = $roleForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members/'.$member->getUuid().'/role', [
            '_token' => $token,
            'role' => 'not-a-role',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Invalid membership role');
    }
}
