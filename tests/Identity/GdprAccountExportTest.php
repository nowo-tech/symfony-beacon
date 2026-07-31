<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Exception\AccountAnonymizeException;
use App\Identity\Repository\UserRepository;
use App\Identity\Service\AccountAnonymizer;
use App\Project\Entity\ProjectMembership;
use App\Shared\Menu\DashboardMenuDemoSeeder;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class GdprAccountExportTest extends DatabaseWebTestCase
{
    public function testUserCanExportAccountJsonWithoutSecrets(): void
    {
        [$client, $user] = $this->bootWithDemoProject('gdpr-export@example.com', 'ExportSecret1!');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="account-privacy-export"]');

        $client->request(Request::METHOD_GET, '/account/privacy/export');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/json; charset=UTF-8');
        $disposition = (string) $client->getResponse()->headers->get('content-disposition');
        self::assertStringContainsString('beacon-account-'.$user->getUuid().'.json', $disposition);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('beacon-account-export/v1', $payload['schema']);
        self::assertSame($user->getUuid(), $payload['account']['uuid']);
        self::assertSame('gdpr-export@example.com', $payload['account']['email']);
        self::assertArrayHasKey('project_memberships', $payload);
        self::assertArrayHasKey('notes', $payload);
        $json = (string) $client->getResponse()->getContent();
        self::assertStringNotContainsString('$2y$', $json);
        self::assertStringNotContainsString('$argon', $json);
        self::assertStringNotContainsString('ExportSecret1!', $json);
    }

    public function testAnonymizeBlockedForSoleOwnerThenSucceedsAfterSecondOwner(): void
    {
        [$client, $user] = $this->bootWithDemoProject('gdpr-anon@example.com', 'AnonSecret1!Abc');
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/account/privacy');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-testid="account-privacy-anonymize-open"]');

        $anonymizer = self::getContainer()->get(AccountAnonymizer::class);
        try {
            $anonymizer->anonymize($user, $user);
            self::fail('Expected sole-owner anonymize to fail');
        } catch (AccountAnonymizeException $e) {
            self::assertSame(AccountAnonymizeException::SOLE_OWNER, $e->reasonCode);
        }

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $other = new User();
        $other->setEmail('gdpr-coowner@example.com');
        $other->setDisplayName('Co Owner');
        $other->setRoles(['ROLE_ADMIN']);
        $other->setPassword($hasher->hashPassword($other, 'CoOwnerSecret1!'));
        $em->persist($other);

        $user = $em->find(User::class, $user->getId());
        self::assertInstanceOf(User::class, $user);
        $memberships = self::getContainer()->get(\App\Project\Repository\ProjectMembershipRepository::class)->findByUser($user);
        self::assertNotEmpty($memberships);
        $project = $memberships[0]->getProject();
        self::assertNotNull($project);

        $coOwner = new ProjectMembership();
        $coOwner->setProject($project);
        $coOwner->setUser($other);
        $coOwner->setRole(ProjectRole::Owner);
        $em->persist($coOwner);
        $em->flush();

        $this->login($client, $user);
        $crawler = $client->request(Request::METHOD_GET, '/account/privacy');
        self::assertSelectorExists('[data-testid="account-privacy-anonymize-open"]');
        $token = (string) $crawler->filter('form[action$="/account/privacy/anonymize"] input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/account/privacy/anonymize', ['_token' => $token]);
        self::assertTrue($client->getResponse()->isRedirect());

        $fresh = self::getContainer()->get(UserRepository::class)->find($user->getId());
        self::assertInstanceOf(User::class, $fresh);
        self::assertTrue($fresh->isAnonymized());
        self::assertFalse($fresh->isEnabled());
        self::assertStringStartsWith('anonymized-', $fresh->getEmail());
        self::assertSame('Anonymized user', $fresh->getDisplayName());
    }

    public function testAdminCanExportAnotherUser(): void
    {
        [$client, $admin] = $this->bootWithDemoProject('gdpr-admin-export@example.com', 'AdminExport1!');
        $admin->setRoles(['ROLE_ADMIN']);
        self::getContainer()->get(EntityManagerInterface::class)->flush();
        self::getContainer()->get(DashboardMenuDemoSeeder::class)->seedIfEmpty();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $member = new User();
        $member->setEmail('gdpr-member-export@example.com');
        $member->setDisplayName('Member Export');
        $member->setPassword($hasher->hashPassword($member, 'MemberSecret1!'));
        $em->persist($member);
        $em->flush();

        $this->login($client, $admin);
        $client->request(Request::METHOD_GET, '/admin/users/'.$member->getUuid().'/export');
        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame($member->getUuid(), $payload['account']['uuid']);
        self::assertSame('gdpr-member-export@example.com', $payload['account']['email']);
    }
}
