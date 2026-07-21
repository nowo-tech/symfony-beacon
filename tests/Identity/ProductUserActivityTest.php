<?php

declare(strict_types=1);

namespace App\Tests\Identity;

use App\Identity\Entity\User;
use App\Identity\Entity\UserAction;
use App\Identity\UserActionType;
use App\Issues\Entity\Issue;
use App\Project\Entity\ProjectMembership;
use App\Shared\ProjectRole;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ProductUserActivityTest extends DatabaseWebTestCase
{
    public function testOpeningProjectAndSettingsRecordsUserActions(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('product-open@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $this->login($client, $owner);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/issues');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/settings');
        self::assertResponseIsSuccessful();

        $actions = $em->getRepository(UserAction::class)->findBy(
            ['actor' => $owner],
            ['id' => 'ASC'],
        );
        $types = array_map(static fn (UserAction $a): UserActionType => $a->getAction(), $actions);
        self::assertContains(UserActionType::ProjectOpened, $types);
        self::assertContains(UserActionType::ProjectSettingsViewed, $types);
    }

    public function testAssignAndStatusAlsoRecordUserActions(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('product-issue@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $member = new User();
        $member->setEmail('product-member@example.com');
        $member->setDisplayName('Product Member');
        $member->setPassword($hasher->hashPassword($member, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($member);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'product-activity'));
        $issue->setTitle('Product activity issue');
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

        $form = $crawler->filter('form.issue-assignee-form')->form();
        $assigneeField = $form->get('issue_assignee[assignee]');
        self::assertInstanceOf(ChoiceFormField::class, $assigneeField);
        $assigneeField->disableValidation();
        $assigneeField->setValue((string) $member->getId());
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();

        $crawler = $client->getCrawler();
        $resolveForm = $crawler->filter('form.issue-status-actions__form')->reduce(
            static fn ($node) => str_contains($node->html(), 'value="resolved"')
        )->form();
        $client->submit($resolveForm);
        self::assertResponseRedirects();

        $em->clear();
        $actions = $em->getRepository(UserAction::class)->findBy([], ['id' => 'ASC']);
        $types = array_map(static fn (UserAction $a): UserActionType => $a->getAction(), $actions);
        self::assertContains(UserActionType::IssueOpened, $types);
        self::assertContains(UserActionType::IssueAssigned, $types);
        self::assertContains(UserActionType::IssueStatusChanged, $types);

        $assigned = null;
        $statusChanged = null;
        foreach ($actions as $action) {
            if (UserActionType::IssueAssigned === $action->getAction()) {
                $assigned = $action;
            }
            if (UserActionType::IssueStatusChanged === $action->getAction()) {
                $statusChanged = $action;
            }
        }
        self::assertNotNull($assigned);
        self::assertSame($member->getId(), $assigned->getSubjectUser()?->getId());
        self::assertSame('Product activity issue', $assigned->getContext()['issue_title'] ?? null);

        self::assertNotNull($statusChanged);
        self::assertSame('unresolved', $statusChanged->getContext()['from'] ?? null);
        self::assertSame('resolved', $statusChanged->getContext()['to'] ?? null);

        $ownerId = $owner->getId();
        $ownerUuid = $owner->getUuid();
        self::assertNotNull($ownerId);
        $admin = $em->find(User::class, $ownerId);
        self::assertInstanceOf(User::class, $admin);
        $admin->setRoles(['ROLE_ADMIN']);
        $em->flush();
        $this->login($client, $admin);

        $client->request(Request::METHOD_GET, '/admin/users/'.$ownerUuid.'/activity');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Issue assignee changed');
        self::assertSelectorTextContains('body', 'Issue status changed');
    }
}
