<?php

declare(strict_types=1);

namespace App\Tests\Functional\Project;

use App\Identity\Entity\User;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectMembersTest extends DatabaseWebTestCase
{
    public function testOwnerCanAddMemberWithRole(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-members@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $invitee = new User();
        $invitee->setEmail('invitee@example.com');
        $invitee->setDisplayName('Invitee');
        $invitee->setPassword($hasher->hashPassword($invitee, 'secret'));
        $em->persist($invitee);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action$="/members"]');

        $token = $crawler->filter('form[action$="/members"] input[name="project_member_add[_token]"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members', [
            'project_member_add' => [
                '_token' => $token,
                'email' => 'invitee@example.com',
                'role' => 'admin',
            ],
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/access');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Member added');

        $em->clear();
        $membership = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $invitee->getId(),
        ]);
        self::assertInstanceOf(ProjectMembership::class, $membership);
        self::assertSame(ProjectRole::Admin, $membership->getRole());
    }

    public function testMemberCannotAddMembers(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-deny-add@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('plain-member@example.com');
        $member->setDisplayName('Plain Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members', [
            '_token' => 'x',
            'email' => 'someone@example.com',
            'role' => 'member',
        ]);
        self::assertResponseStatusCodeSame(403);
        unset($owner);
    }

    public function testAddedMemberCanAccessAndStrangerCannot(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-access@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('new-member@example.com');
        $member->setDisplayName('New Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $em->persist($member);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        $token = $crawler->filter('form[action$="/members"] input[name="project_member_add[_token]"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members', [
            'project_member_add' => [
                '_token' => $token,
                'email' => 'new-member@example.com',
                'role' => 'member',
            ],
        ]);
        self::assertResponseRedirects();

        $this->login($client, $member);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();

        $stranger = new User();
        $stranger->setEmail('still-stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerCanChangeRoleAndRemoveMember(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-role@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('role-target@example.com');
        $member->setDisplayName('Role Target');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        $roleForm = $crawler->filter('form[action$="/members/'.$member->getUuid().'/role"]');
        self::assertGreaterThan(0, $roleForm->count());
        $token = $roleForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members/'.$member->getUuid().'/role', [
            '_token' => $token,
            'role' => 'admin',
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/access');

        $em->clear();
        $reloaded = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $member->getId(),
        ]);
        self::assertSame(ProjectRole::Admin, $reloaded?->getRole());

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        $removeForm = $crawler->filter('form[action$="/members/'.$member->getUuid().'/remove"]');
        $token = $removeForm->filter('input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members/'.$member->getUuid().'/remove', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/access');

        $em->clear();
        self::assertNull($em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $member->getId(),
        ]));
    }

    public function testOwnerRowHasNoEditOrRemoveActions(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-last@example.com');
        $this->login($client, $owner);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/members/'.$owner->getUuid().'/role"]');
        self::assertSelectorNotExists('form[action$="/members/'.$owner->getUuid().'/remove"]');
    }

    public function testOwnerCanTransferOwnership(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-transfer@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('new-owner@example.com');
        $member->setDisplayName('New Owner');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/danger');
        self::assertResponseIsSuccessful();

        $transferForm = $crawler->filter('form[action$="/transfer-ownership"]');
        self::assertGreaterThan(0, $transferForm->count());
        $client->submit($transferForm->form([
            'project_transfer_ownership[user]' => $member->getUuid(),
            'project_transfer_ownership[confirmation]' => $project->getName(),
        ]));
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/danger');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'ownership transferred');

        $em->clear();
        $ownerMembership = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $owner->getId(),
        ]);
        $newOwnerMembership = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $member->getId(),
        ]);
        self::assertSame(ProjectRole::Full, $ownerMembership?->getRole());
        self::assertSame(ProjectRole::Owner, $newOwnerMembership?->getRole());
    }

    public function testTransferOwnershipRequiresProjectNameConfirmation(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-transfer-confirm@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('keep-member@example.com');
        $member->setDisplayName('Keep Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Admin);
        $project->addMembership($membership);
        $em->persist($member);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/danger');
        $client->submit($crawler->filter('form[action$="/transfer-ownership"]')->form([
            'project_transfer_ownership[user]' => $member->getUuid(),
            'project_transfer_ownership[confirmation]' => 'wrong-name',
        ]));
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/danger');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'did not match');

        $em->clear();
        $ownerMembership = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $owner->getId(),
        ]);
        self::assertSame(ProjectRole::Owner, $ownerMembership?->getRole());
    }

    public function testAdminCannotTransferOwnership(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-transfer-deny@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $admin = new User();
        $admin->setEmail('project-admin@example.com');
        $admin->setDisplayName('Project Admin');
        $admin->setPassword($hasher->hashPassword($admin, 'secret'));
        $adminMembership = new ProjectMembership();
        $adminMembership->setUser($admin);
        $adminMembership->setRole(ProjectRole::Admin);
        $project->addMembership($adminMembership);

        $member = new User();
        $member->setEmail('transfer-target@example.com');
        $member->setDisplayName('Transfer Target');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $memberMembership = new ProjectMembership();
        $memberMembership->setUser($member);
        $memberMembership->setRole(ProjectRole::Member);
        $project->addMembership($memberMembership);

        $em->persist($admin);
        $em->persist($member);
        $em->flush();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/transfer-ownership"]');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/transfer-ownership', [
            '_token' => 'x',
            'user' => $member->getUuid(),
            'confirmation' => $project->getName(),
        ]);
        self::assertResponseStatusCodeSame(403);
        unset($owner);
    }

    public function testCannotRemoveFullMemberWithoutDemotingFirst(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('owner-remove-full@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $full = new User();
        $full->setEmail('full-member@example.com');
        $full->setDisplayName('Full Member');
        $full->setPassword($hasher->hashPassword($full, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($full);
        $membership->setRole(ProjectRole::Full);
        $project->addMembership($membership);
        $em->persist($full);
        $em->flush();

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        $removeForm = $crawler->filter('form[action$="/members/'.$full->getUuid().'/remove"]');
        self::assertGreaterThan(0, $removeForm->count());
        $token = $removeForm->filter('input[name="_token"]')->attr('value');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/members/'.$full->getUuid().'/remove', [
            '_token' => $token,
        ]);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/settings/access');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash', 'full role');

        $em->clear();
        $stillThere = $em->getRepository(ProjectMembership::class)->findOneBy([
            'project' => $project->getId(),
            'user' => $full->getId(),
        ]);
        self::assertSame(ProjectRole::Full, $stillThere?->getRole());
    }

    public function testFullMemberCannotTransferOwnership(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('full-transfer-deny@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $full = new User();
        $full->setEmail('full-actor@example.com');
        $full->setDisplayName('Full Actor');
        $full->setPassword($hasher->hashPassword($full, 'secret'));
        $fullMembership = new ProjectMembership();
        $fullMembership->setUser($full);
        $fullMembership->setRole(ProjectRole::Full);
        $project->addMembership($fullMembership);

        $member = new User();
        $member->setEmail('full-transfer-target@example.com');
        $member->setDisplayName('Transfer Target');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $memberMembership = new ProjectMembership();
        $memberMembership->setUser($member);
        $memberMembership->setRole(ProjectRole::Member);
        $project->addMembership($memberMembership);

        $em->persist($full);
        $em->persist($member);
        $em->flush();

        $this->login($client, $full);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings/access');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/transfer-ownership"]');

        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/transfer-ownership', [
            '_token' => 'x',
            'user' => $member->getUuid(),
            'confirmation' => $project->getName(),
        ]);
        self::assertResponseStatusCodeSame(403);
        unset($owner);
    }
}
