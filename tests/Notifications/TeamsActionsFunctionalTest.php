<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\InteractionActionToken;
use App\Project\Entity\ProjectMembership;
use App\Shared\IssueStatus;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TeamsActionsFunctionalTest extends DatabaseWebTestCase
{
    public function testResolveHttpPostRejectedWhenAnonymousDisabled(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('teams-resolve@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/x');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-resolve'));
        $issue->setTitle('Teams resolve me');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $token = new InteractionActionToken()->issueResolveToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );
        $body = json_encode($token, \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/hooks/teams/actions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        );

        self::assertResponseStatusCodeSame(403);

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Unresolved, $reloaded->getStatus());
    }

    public function testRejectsInvalidTokenWithoutMutatingIssue(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('teams-bad-token@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/y');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-bad'));
        $issue->setTitle('Keep unresolved');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $token = new InteractionActionToken()->issueResolveToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );
        $token['sig'] = 'deadbeef';
        $body = json_encode($token, \JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            '/hooks/teams/actions',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $body,
        );

        self::assertResponseStatusCodeSame(401);

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Unresolved, $reloaded->getStatus());
    }

    public function testAssignMeOpenUriAssignsLoggedInOwner(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('teams-assign-ok@example.com');
        $this->login($client, $owner);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/assign');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-assign-ok'));
        $issue->setTitle('Assign me');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $token = new InteractionActionToken()->issueAssignToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );

        $client->request(Request::METHOD_GET, '/hooks/teams/assign-me?'.http_build_query($token));

        self::assertResponseRedirects();
        self::assertStringContainsString('/issues/'.$issue->getUuid(), (string) $client->getResponse()->headers->get('Location'));

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertNotNull($reloaded->getAssignee());
        self::assertSame($owner->getId(), $reloaded->getAssignee()->getId());
    }

    public function testAssignMeAnonymousRedirectsToLogin(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('teams-assign-anon@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/anon');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-assign-anon'));
        $issue->setTitle('Anon assign');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $token = new InteractionActionToken()->issueAssignToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );

        $client->request(Request::METHOD_GET, '/hooks/teams/assign-me?'.http_build_query($token));

        self::assertResponseRedirects();
        $location = (string) $client->getResponse()->headers->get('Location');
        self::assertTrue(
            str_contains($location, '/login') || str_contains($location, 'login'),
            'Expected login redirect, got: '.$location,
        );

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertNull($reloaded->getAssignee());
    }

    public function testAssignMeRejectsBadToken(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('teams-assign-bad@example.com');
        $this->login($client, $owner);
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/bad');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-assign-bad'));
        $issue->setTitle('Bad token');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $token = new InteractionActionToken()->issueAssignToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );
        $token['sig'] = 'deadbeef';

        $client->request(Request::METHOD_GET, '/hooks/teams/assign-me?'.http_build_query($token));

        self::assertResponseStatusCodeSame(403);

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertNull($reloaded->getAssignee());
    }

    public function testAssignMeViewerWithoutTriageDoesNotAssign(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('teams-assign-viewer-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $viewer = new User();
        $viewer->setEmail('teams-assign-viewer@example.com');
        $viewer->setDisplayName('Viewer');
        $viewer->setPassword($hasher->hashPassword($viewer, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($viewer);
        $membership->setRole(ProjectRole::Viewer);
        $project->addMembership($membership);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Teams');
        $destination->setType(NotificationDestinationType::Teams);
        $destination->setEndpointUrl('https://outlook.office.com/webhook/viewer');
        $destination->setSigningSecret('teams-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'teams-assign-viewer'));
        $issue->setTitle('Viewer cannot assign');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($viewer);
        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $this->login($client, $viewer);

        $token = new InteractionActionToken()->issueAssignToken(
            'teams-signing-secret',
            $destination->getUuid(),
            $project->getUuid(),
            $issue->getUuid(),
        );

        $client->request(Request::METHOD_GET, '/hooks/teams/assign-me?'.http_build_query($token));

        self::assertResponseRedirects();

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertNull($reloaded->getAssignee());
    }
}
