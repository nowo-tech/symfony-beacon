<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Project\Entity\ProjectMembership;
use App\Project\Entity\ProjectShareLink;
use App\Project\Service\ProjectShareLinkManager;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProjectShareLinkTest extends DatabaseWebTestCase
{
    public function testShareLinkGrantsViewerAccessThenExpires(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('share-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $recipient = new User();
        $recipient->setEmail('share-recipient@example.com');
        $recipient->setDisplayName('Recipient');
        $recipient->setPassword($hasher->hashPassword($recipient, 'secret'));
        $em->persist($recipient);
        $em->flush();

        /** @var ProjectShareLinkManager $manager */
        $manager = self::getContainer()->get(ProjectShareLinkManager::class);
        $created = $manager->create($project, $owner, null, new DateTimeImmutable('+1 day'));
        $token = $created['rawToken'];

        $this->login($client, $recipient);
        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        // Recipient is not a member but share grant allows issues list.
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();

        $created['link']->setExpiresAt(new DateTimeImmutable('-1 hour'));
        $em->flush();
        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects();
    }

    public function testIssueScopedShareDoesNotUnlockIssueList(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('share-issue-scope@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint('share-scope-fp');
        $issue->setTitle('Scoped issue');
        $issue->setCulprit('App\\Scoped');
        $em->persist($issue);

        $other = new Issue();
        $other->setProject($project);
        $other->setFingerprint('share-other-fp');
        $other->setTitle('Other issue');
        $other->setCulprit('App\\Other');
        $em->persist($other);

        $recipient = new User();
        $recipient->setEmail('share-issue-user@example.com');
        $recipient->setDisplayName('Recipient');
        $recipient->setPassword($hasher->hashPassword($recipient, 'secret'));
        $em->persist($recipient);
        $em->flush();

        /** @var ProjectShareLinkManager $manager */
        $manager = self::getContainer()->get(ProjectShareLinkManager::class);
        $created = $manager->create($project, $owner, $issue, new DateTimeImmutable('+1 day'));

        $this->login($client, $recipient);
        $client->request(Request::METHOD_GET, '/share/'.$created['rawToken']);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$other->getUuid());
        self::assertResponseStatusCodeSame(403);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        self::assertResponseIsSuccessful();
    }

    public function testRevokedShareLinkIsRejected(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('share-revoke@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $recipient = new User();
        $recipient->setEmail('share-revoke-user@example.com');
        $recipient->setDisplayName('Recipient');
        $recipient->setPassword($hasher->hashPassword($recipient, 'secret'));
        // Give membership so login works without share; then revoke share and assert open fails.
        $membership = new ProjectMembership();
        $membership->setUser($recipient);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($recipient);
        $em->flush();

        /** @var ProjectShareLinkManager $manager */
        $manager = self::getContainer()->get(ProjectShareLinkManager::class);
        $created = $manager->create($project, $owner, null, new DateTimeImmutable('+1 day'));
        $manager->revoke($created['link'], $owner);

        $this->login($client, $recipient);
        $client->request(Request::METHOD_GET, '/share/'.$created['rawToken']);
        self::assertResponseRedirects('/en/login');
    }

    public function testShareLinkMaxUsesExhaustedAfterSingleOpen(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('share-max-uses@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $recipient = new User();
        $recipient->setEmail('share-max-uses-user@example.com');
        $recipient->setDisplayName('Recipient');
        $recipient->setPassword($hasher->hashPassword($recipient, 'secret'));
        $em->persist($recipient);
        $em->flush();

        /** @var ProjectShareLinkManager $manager */
        $manager = self::getContainer()->get(ProjectShareLinkManager::class);
        $created = $manager->create($project, $owner, null, new DateTimeImmutable('+1 day'), 1);
        $token = $created['rawToken'];

        $this->login($client, $recipient);
        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $em->clear();
        $link = $em->find(ProjectShareLink::class, $created['link']->getId());
        self::assertInstanceOf(ProjectShareLink::class, $link);
        self::assertSame(1, $link->getUseCount());
        self::assertFalse($link->isUsable());

        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects('/en/login');
    }

    public function testUnlimitedShareLinkAllowsMultipleOpens(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('share-unlimited@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $recipient = new User();
        $recipient->setEmail('share-unlimited-user@example.com');
        $recipient->setDisplayName('Recipient');
        $recipient->setPassword($hasher->hashPassword($recipient, 'secret'));
        $em->persist($recipient);
        $em->flush();

        /** @var ProjectShareLinkManager $manager */
        $manager = self::getContainer()->get(ProjectShareLinkManager::class);
        $created = $manager->create($project, $owner, null, new DateTimeImmutable('+1 day'));
        $token = $created['rawToken'];
        $linkId = $created['link']->getId();

        $this->login($client, $recipient);
        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/share/'.$token);
        self::assertResponseRedirects('/projects/'.$project->getUuid().'/issues');
        $em->clear();
        $link = $em->find(ProjectShareLink::class, $linkId);
        self::assertInstanceOf(ProjectShareLink::class, $link);
        self::assertSame(2, $link->getUseCount());
        self::assertTrue($link->isUsable());
    }
}
