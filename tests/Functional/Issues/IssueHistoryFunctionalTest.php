<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueHistoryEntry;
use App\Issues\Enum\IssueStatus;
use App\Issues\IssueHistoryKind;
use App\Project\Entity\ProjectMembership;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class IssueHistoryFunctionalTest extends DatabaseWebTestCase
{
    public function testAssignAndResolveRecordHistory(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('history-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('history-member@example.com');
        $member->setDisplayName('History Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'history-demo'));
        $issue->setTitle('History issue');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->incrementEventCount();

        $em->persist($member);
        $em->persist($issue);
        $em->flush();

        $this->login($client, $owner);
        $issuePath = '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid();
        $historyPath = $issuePath.'/history';
        $crawler = $client->request(Request::METHOD_GET, $issuePath);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Activity');
        self::assertSelectorTextContains('body', 'Mark resolved');

        $assignToken = $crawler->filter('form.issue-assignee-form input[name="issue_assignee[_token]"]')->attr('value');
        self::assertNotNull($assignToken);
        $client->request(Request::METHOD_POST, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid().'/assign', [
            'issue_assignee' => [
                '_token' => $assignToken,
                'assignee' => (string) $member->getId(),
            ],
        ]);
        self::assertResponseRedirects();
        $client->followRedirect();
        $client->request(Request::METHOD_GET, $historyPath);
        self::assertSelectorTextContains('.issue-history', 'History Member');

        $crawler = $client->request(Request::METHOD_GET, $issuePath);
        $resolveForm = $crawler->filter('form.issue-status-actions__form')->reduce(
            static fn ($node): bool => str_contains((string) $node->html(), 'value="resolved"')
        )->form();
        $client->submit($resolveForm);
        self::assertResponseRedirects();
        $client->followRedirect();
        $client->request(Request::METHOD_GET, $historyPath);
        self::assertSelectorTextContains('.issue-badge--status', 'Resolved');
        self::assertSelectorTextContains('.issue-history', 'Resolved');

        $em->clear();
        /** @var Issue $reloaded */
        $reloaded = $em->getRepository(Issue::class)->find($issue->getId());
        self::assertSame(IssueStatus::Resolved, $reloaded->getStatus());
        self::assertSame($member->getId(), $reloaded->getAssignee()?->getId());

        $entries = $em->getRepository(IssueHistoryEntry::class)->findBy(['issue' => $reloaded], ['id' => 'ASC']);
        self::assertCount(2, $entries);
        self::assertSame(IssueHistoryKind::AssigneeChanged, $entries[0]->getKind());
        self::assertSame(IssueHistoryKind::StatusChanged, $entries[1]->getKind());
        self::assertSame(IssueStatus::Unresolved, $entries[1]->getFromStatus());
        self::assertSame(IssueStatus::Resolved, $entries[1]->getToStatus());
        self::assertSame($owner->getId(), $entries[1]->getActor()?->getId());
    }
}
