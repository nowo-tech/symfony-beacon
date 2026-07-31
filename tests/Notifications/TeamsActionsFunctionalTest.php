<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Notifications\Service\InteractionActionToken;
use App\Shared\IssueStatus;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class TeamsActionsFunctionalTest extends DatabaseWebTestCase
{
    public function testResolveHttpPostMarksIssueResolvedWithValidToken(): void
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

        self::assertResponseIsSuccessful();

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Resolved, $reloaded->getStatus());
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
}
