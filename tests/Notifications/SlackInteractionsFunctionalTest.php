<?php

declare(strict_types=1);

namespace App\Tests\Notifications;

use App\Issues\Entity\Issue;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Shared\IssueStatus;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SlackInteractionsFunctionalTest extends DatabaseWebTestCase
{
    public function testResolveButtonMarksIssueResolvedWithValidSignature(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('slack-resolve@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Slack');
        $destination->setType(NotificationDestinationType::Slack);
        $destination->setEndpointUrl('https://hooks.slack.com/services/T/B/X');
        $destination->setSigningSecret('slack-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'slack-resolve'));
        $issue->setTitle('Slack resolve me');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $value = json_encode([
            'a' => 'resolve',
            'd' => $destination->getUuid(),
            'p' => $project->getUuid(),
            'i' => $issue->getUuid(),
        ], \JSON_THROW_ON_ERROR);

        $interaction = json_encode([
            'type' => 'block_actions',
            'actions' => [[
                'action_id' => 'beacon_resolve',
                'value' => $value,
            ]],
        ], \JSON_THROW_ON_ERROR);

        $body = http_build_query(['payload' => $interaction]);
        $ts = (string) time();
        $sig = 'v0='.hash_hmac('sha256', 'v0:'.$ts.':'.$body, 'slack-signing-secret');

        $client->request(
            Request::METHOD_POST,
            '/hooks/slack/interactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts,
                'HTTP_X_SLACK_SIGNATURE' => $sig,
            ],
            $body,
        );

        self::assertResponseIsSuccessful();

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Resolved, $reloaded->getStatus());
    }

    public function testRejectsInvalidSignatureWithoutMutatingIssue(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('slack-bad-sig@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Ops Slack');
        $destination->setType(NotificationDestinationType::Slack);
        $destination->setEndpointUrl('https://hooks.slack.com/services/T/B/Y');
        $destination->setSigningSecret('slack-signing-secret');
        $destination->setEnabled(true);
        $destination->setCategories(['error']);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'slack-bad-sig'));
        $issue->setTitle('Keep unresolved');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($destination);
        $em->persist($issue);
        $em->flush();

        $value = json_encode([
            'a' => 'resolve',
            'd' => $destination->getUuid(),
            'p' => $project->getUuid(),
            'i' => $issue->getUuid(),
        ], \JSON_THROW_ON_ERROR);

        $interaction = json_encode([
            'type' => 'block_actions',
            'actions' => [[
                'action_id' => 'beacon_resolve',
                'value' => $value,
            ]],
        ], \JSON_THROW_ON_ERROR);

        $body = http_build_query(['payload' => $interaction]);
        $ts = (string) time();

        $client->request(
            Request::METHOD_POST,
            '/hooks/slack/interactions',
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
                'HTTP_X_SLACK_REQUEST_TIMESTAMP' => $ts,
                'HTTP_X_SLACK_SIGNATURE' => 'v0=deadbeef',
            ],
            $body,
        );

        self::assertResponseStatusCodeSame(401);

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Unresolved, $reloaded->getStatus());
    }
}
