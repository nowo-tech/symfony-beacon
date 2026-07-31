<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueComment;
use App\Issues\Service\IssueMentionParser;
use App\Issues\Service\IssueUserMailNotifier;
use App\Issues\Service\IssueUserMailTransport;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class IssueMentionsNotifyTest extends DatabaseWebTestCase
{
    public function testMentionParserResolvesProjectMembersOnly(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('mention-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $alice = new User();
        $alice->setEmail('alice@example.com');
        $alice->setDisplayName('Alice');
        $alice->setPassword($hasher->hashPassword($alice, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($alice);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($alice);

        $outsider = new User();
        $outsider->setEmail('outsider@example.com');
        $outsider->setDisplayName('Outsider');
        $outsider->setPassword($hasher->hashPassword($outsider, 'secret'));
        $em->persist($outsider);
        $em->flush();

        /** @var IssueMentionParser $parser */
        $parser = self::getContainer()->get(IssueMentionParser::class);
        $mentioned = $parser->resolveMentions($project, 'Hey @Alice and @outsider@example.com please look', $owner);

        self::assertCount(1, $mentioned);
        self::assertSame('alice@example.com', $mentioned[0]->getEmail());
    }

    public function testNotifierSkipsWhenMailerUnavailable(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('mention-nomail@example.com');
        $issue = $this->persistIssue($project, 'no-mail-fp');

        $comment = new IssueComment();
        $comment->setIssue($issue);
        $comment->setAuthor($owner);
        $comment->setBody('Hi @nobody');

        $transport = new class implements IssueUserMailTransport {
            public int $sent = 0;

            public function isAvailable(): bool
            {
                return false;
            }

            public function getFromAddress(): string
            {
                return 'beacon@example.com';
            }

            public function send(Email $email): void
            {
                ++$this->sent;
            }
        };

        $notifier = new IssueUserMailNotifier(
            $transport,
            self::getContainer()->get(IssueMentionParser::class),
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(UrlGeneratorInterface::class),
            new NullLogger(),
        );
        $notifier->notifyMentionsFromComment($project, $issue, $comment, $owner);
        $notifier->notifyAssigneeChanged($project, $issue, null, $owner, $owner);
        self::assertSame(0, $transport->sent);
    }

    public function testNotifierSendsOnMentionAndAssign(): void
    {
        [, $owner, $project] = $this->bootWithDemoProject('mention-mail@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $alice = new User();
        $alice->setEmail('alice-mail@example.com');
        $alice->setDisplayName('AliceMail');
        $alice->setPassword($hasher->hashPassword($alice, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($alice);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($alice);
        $em->flush();

        $issue = $this->persistIssue($project, 'mail-fp');
        $comment = new IssueComment();
        $comment->setIssue($issue);
        $comment->setAuthor($owner);
        $comment->setBody('Ping @AliceMail');

        $transport = new class implements IssueUserMailTransport {
            /** @var list<string> */
            public array $recipients = [];

            public function isAvailable(): bool
            {
                return true;
            }

            public function getFromAddress(): string
            {
                return 'beacon@example.com';
            }

            public function send(Email $email): void
            {
                $this->recipients[] = $email->getTo()[0]->getAddress();
            }
        };

        $notifier = new IssueUserMailNotifier(
            $transport,
            self::getContainer()->get(IssueMentionParser::class),
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(UrlGeneratorInterface::class),
            new NullLogger(),
        );
        $notifier->notifyMentionsFromComment($project, $issue, $comment, $owner);
        $notifier->notifyAssigneeChanged($project, $issue, null, $alice, $owner);
        self::assertSame(['alice-mail@example.com', 'alice-mail@example.com'], $transport->recipients);
    }

    public function testCommentWithMentionDoesNotCrashWithoutMailer(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('mention-http@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $alice = new User();
        $alice->setEmail('alice-http@example.com');
        $alice->setDisplayName('AliceHttp');
        $alice->setPassword($hasher->hashPassword($alice, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($alice);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($alice);

        $issue = $this->persistIssue($project, 'http-fp');
        $this->login($client, $owner);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('form.issue-comments__form input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/comments', [
            '_token' => $token,
            'body' => 'Hello @AliceHttp',
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-testid="issue-comments"]', 'Hello @AliceHttp');
    }

    public function testCommentMentionUsesInjectedTransport(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('mention-send@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $alice = new User();
        $alice->setEmail('alice-send@example.com');
        $alice->setDisplayName('AliceSend');
        $alice->setPassword($hasher->hashPassword($alice, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($alice);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);
        $em->persist($alice);

        $issue = $this->persistIssue($project, 'send-fp');

        $transport = new class implements IssueUserMailTransport {
            public int $sent = 0;

            public function isAvailable(): bool
            {
                return true;
            }

            public function getFromAddress(): string
            {
                return 'beacon@example.com';
            }

            public function send(Email $email): void
            {
                ++$this->sent;
            }
        };

        $notifier = new IssueUserMailNotifier(
            $transport,
            self::getContainer()->get(IssueMentionParser::class),
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(UrlGeneratorInterface::class),
            new NullLogger(),
        );
        $client->disableReboot();
        $client->getContainer()->set(IssueUserMailNotifier::class, $notifier);

        $this->login($client, $owner);
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        $token = $crawler->filter('form.issue-comments__form input[name="_token"]')->attr('value');
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/comments', [
            '_token' => $token,
            'body' => 'Hello @AliceSend',
        ]);
        self::assertResponseRedirects();
        self::assertSame(1, $transport->sent);
    }

    private function persistIssue(Project $project, string $fingerprintSeed): Issue
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $fingerprintSeed));
        $issue->setTitle('Mention test issue');
        $issue->setCulprit('App\\Mention');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();
        $em->persist($issue);
        $em->flush();

        return $issue;
    }
}
