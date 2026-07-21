<?php

declare(strict_types=1);

namespace App\Tests\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueHistoryEntry;
use App\Issues\IssueHistoryKind;
use App\Project\Entity\ProjectMembership;
use App\Shared\IssueStatus;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
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
        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues/'.$issue->getUuid());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Activity');
        self::assertSelectorTextContains('body', 'Mark resolved');

        $form = $crawler->filter('form.issue-assignee-form')->form();
        $assigneeField = $form->get('issue_assignee[assignee]');
        self::assertInstanceOf(ChoiceFormField::class, $assigneeField);
        $assigneeField->disableValidation();
        $assigneeField->setValue((string) $member->getId());
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.issue-history', 'History Member');

        $crawler = $client->getCrawler();
        $resolveForm = $crawler->filter('form.issue-status-actions__form')->reduce(
            static fn ($node): bool => str_contains((string) $node->html(), 'value="resolved"')
        )->form();
        $client->submit($resolveForm);
        self::assertResponseRedirects();
        $client->followRedirect();
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
