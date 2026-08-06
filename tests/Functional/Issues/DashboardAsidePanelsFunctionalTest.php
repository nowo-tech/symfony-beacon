<?php

declare(strict_types=1);

namespace App\Tests\Functional\Issues;

use App\Identity\Entity\User;
use App\Issues\Entity\Issue;
use App\Issues\Entity\IssueMention;
use App\Issues\Service\IssueCommentCreator;
use App\Notifications\Entity\NotificationDestination;
use App\Notifications\Enum\NotificationDestinationType;
use App\Project\Entity\ProjectMembership;
use App\Issues\Enum\IssueStatus;
use App\Project\Enum\ProjectRole;
use App\Tests\Support\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class DashboardAsidePanelsFunctionalTest extends DatabaseWebTestCase
{
    public function testAsidePanelsRenderAndMentionsInboxWorks(): void
    {
        [$client, $owner, $project] = $this->bootWithDemoProject('aside-owner@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $mate = new User();
        $mate->setEmail('aside-mate@example.com');
        $mate->setDisplayName('Aside Mate');
        $mate->setPassword($hasher->hashPassword($mate, 'secret'));
        $membership = new ProjectMembership();
        $membership->setUser($mate);
        $membership->setRole(ProjectRole::Member);
        $project->addMembership($membership);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'aside-new-release'));
        $issue->setTitle('Release regression');
        $issue->setCulprit('demo');
        $issue->setLevel('error');
        $issue->setStatus(IssueStatus::Unresolved);
        $issue->setFirstSeen(new DateTimeImmutable());
        $issue->setLastSeen(new DateTimeImmutable());
        $issue->setFirstRelease('1.2.3');
        $issue->incrementEventCount();
        $issue->setAssignee($owner);

        $destination = new NotificationDestination();
        $destination->setProject($project);
        $destination->setLabel('Webhook fail');
        $destination->setType(NotificationDestinationType::Http);
        $destination->setEndpointUrl('https://example.test/hook');
        $destination->setEnabled(true);
        $destination->recordDeliveryFailure('connection refused');

        $em->persist($mate);
        $em->persist($issue);
        $em->persist($destination);
        $em->flush();

        self::getContainer()->get(IssueCommentCreator::class)->create(
            $issue,
            $mate,
            'Hey @aside-owner please look',
            'test',
        );

        self::getContainer()->get(\App\Setup\Demo\DashboardMenuDemoSeeder::class)->seedIfEmpty();
        $this->login($client, $owner);

        foreach ([
            '/dashboard/summary' => 'summary-cards',
            '/dashboard/activity' => 'activity-filters',
            '/dashboard/alerts' => 'alerts-results',
            '/dashboard/new-in-release' => 'new-in-release-results',
        ] as $path => $testid) {
            $client->request(Request::METHOD_GET, $path);
            self::assertResponseIsSuccessful($path);
            self::assertSelectorExists('[data-testid="'.$testid.'"]', $path);
        }

        $client->request(Request::METHOD_GET, '/dashboard/mentions');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-testid="mentions-results"]');
        self::assertSelectorExists('.beacon-breadcrumb');
        self::assertSelectorTextContains('.beacon-breadcrumb', 'Mentions');
        self::assertSelectorTextContains('#dashboard-menu-navigation', 'Mentions');
        self::assertSelectorTextContains('#dashboard-menu-navigation', 'Alerts');
        self::assertSelectorTextContains('[data-testid="mentions-results"]', 'Release regression');

        foreach (['/dashboard/summary', '/dashboard/activity', '/dashboard/alerts', '/dashboard/new-in-release', '/dashboard/assignments'] as $crumbPath) {
            $client->request(Request::METHOD_GET, $crumbPath);
            self::assertResponseIsSuccessful($crumbPath);
            self::assertSelectorExists('.beacon-breadcrumb', $crumbPath);
        }

        $client->request(Request::METHOD_GET, '/dashboard/alerts');
        self::assertSelectorTextContains('[data-testid="alerts-results"]', 'Webhook fail');

        $client->request(Request::METHOD_GET, '/dashboard/new-in-release');
        self::assertSelectorTextContains('[data-testid="new-in-release-results"]', 'Release regression');

        $mention = $em->getRepository(IssueMention::class)->findOneBy(['mentionedUser' => $owner]);
        self::assertInstanceOf(IssueMention::class, $mention);
        self::assertTrue($mention->isUnread());

        $crawler = $client->request(Request::METHOD_GET, '/dashboard/mentions');
        $form = $crawler->filter('[data-testid="mentions-results"] form')->form();
        $client->submit($form);
        self::assertResponseRedirects();
        $client->followRedirect();

        $em->clear();
        $mention = $em->getRepository(IssueMention::class)->find($mention->getId());
        self::assertInstanceOf(IssueMention::class, $mention);
        self::assertFalse($mention->isUnread());
    }
}
