<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Service\InboundEmailReplyToken;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

final class InboundEmailFunctionalTest extends DatabaseWebTestCase
{
    public function testValidReplyCreatesComment(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('inbound-ok@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'inbound-ok'));
        $issue->setTitle('Inbound target');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        $token = self::getContainer()->get(InboundEmailReplyToken::class)->issue($issue->getUuid());
        $recipient = 'reply+'.$token.'@inbound.beacon.test';

        $client->request(
            Request::METHOD_POST,
            '/hooks/email/inbound',
            [
                'sender' => 'inbound-ok@example.com',
                'recipient' => $recipient,
                'body-plain' => "Looks good to me.\n\nOn Mon someone wrote:\n> old",
                'Message-Id' => '<inbound-ok-1@example.com>',
            ],
            [],
            ['HTTP_X_BEACON_INBOUND_SECRET' => 'phpunit-inbound-secret'],
        );

        self::assertResponseIsSuccessful();
        self::assertSame('created', $client->getResponse()->getContent());

        /** @var list<IssueComment> $comments */
        $comments = $em->getRepository(IssueComment::class)->findAll();
        self::assertCount(1, $comments);
        self::assertSame('Looks good to me.', $comments[0]->getBody());
        self::assertSame($owner->getId(), $comments[0]->getAuthor()?->getId());
    }

    public function testBadSecretUnauthorized(): void
    {
        [$client] = $this->bootWithDemoProject('inbound-bad@example.com');
        $client->request(Request::METHOD_POST, '/hooks/email/inbound', [
            'sender' => 'x@example.com',
            'recipient' => 'reply+x@inbound.beacon.test',
            'body-plain' => 'hi',
        ], [], ['HTTP_X_BEACON_INBOUND_SECRET' => 'wrong']);

        self::assertResponseStatusCodeSame(401);
    }

    public function testBodyBeaconSecretIsRejected(): void
    {
        [$client] = $this->bootWithDemoProject('inbound-body-secret@example.com');
        $client->request(Request::METHOD_POST, '/hooks/email/inbound', [
            'sender' => 'x@example.com',
            'recipient' => 'reply+x@inbound.beacon.test',
            'body-plain' => 'hi',
            'beacon_secret' => 'phpunit-inbound-secret',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testDuplicateMessageIdIsIdempotent(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('inbound-dup@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'inbound-dup'));
        $issue->setTitle('Dup');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        $token = self::getContainer()->get(InboundEmailReplyToken::class)->issue($issue->getUuid());
        $payload = [
            'sender' => 'inbound-dup@example.com',
            'recipient' => 'reply+'.$token.'@inbound.beacon.test',
            'body-plain' => 'First',
            'Message-Id' => '<dup-1@example.com>',
        ];
        $headers = ['HTTP_X_BEACON_INBOUND_SECRET' => 'phpunit-inbound-secret'];

        $client->request(Request::METHOD_POST, '/hooks/email/inbound', $payload, [], $headers);
        self::assertSame('created', $client->getResponse()->getContent());

        $client->request(Request::METHOD_POST, '/hooks/email/inbound', $payload, [], $headers);
        self::assertSame('duplicate', $client->getResponse()->getContent());
        self::assertSame(1, $em->getRepository(IssueComment::class)->count([]));
    }
}
