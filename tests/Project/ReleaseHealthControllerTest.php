<?php

declare(strict_types=1);

namespace App\Tests\Project;

use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class ReleaseHealthControllerTest extends DatabaseWebTestCase
{
    public function testReleaseHealthPanelShowsCountsEmptyStateCompareAndMembershipProtection(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('release-health@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);

        $this->persistIssue($em, $project, 'New 1.0.0 only', 'release-a.php', '1.0.0', '1.0.0', 'production');
        $this->persistIssue($em, $project, 'Carryover issue', 'release-ab.php', '1.0.0', '2.0.0', 'production');
        $this->persistIssue($em, $project, 'New 2.0.0 only', 'release-b.php', '2.0.0', '2.0.0', 'staging');
        $this->persistEventOnlyRelease($em, $project, 'Event-only release issue', '3.0.0');
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/releases?release=1.0.0&compare=2.0.0',
        );

        self::assertResponseIsSuccessful();
        $body = $crawler->text();
        self::assertStringContainsString('2 issues were first seen in this release.', $body);
        self::assertSelectorExists('a[href*="/issues?release=1.0.0"][href*="status="]');
        self::assertStringContainsString('Compare 1.0.0 vs 2.0.0', $body);
        self::assertStringContainsString('New 1.0.0 only', $body);
        self::assertStringContainsString('Carryover issue', $body);
        self::assertStringContainsString('New 2.0.0 only', $body);
        self::assertStringContainsString('3.0.0', $body);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/releases?release=3.0.0',
        );

        self::assertResponseIsSuccessful();
        $emptyBody = $client->getResponse()->getContent();
        self::assertIsString($emptyBody);
        self::assertStringContainsString('0 issues were first seen in this release.', $emptyBody);
        self::assertStringContainsString('No issues were first seen in this release.', $emptyBody);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stranger = new User();
        $stranger->setEmail('release-health-stranger@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/releases');
        self::assertResponseStatusCodeSame(403);
    }

    private function persistIssue(
        EntityManagerInterface $em,
        Project $project,
        string $title,
        string $culprit,
        ?string $firstRelease,
        ?string $lastRelease,
        ?string $lastEnvironment,
    ): void {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit($culprit);
        $issue->setLevel('error');
        $issue->setFirstRelease($firstRelease);
        $issue->setLastRelease($lastRelease);
        $issue->setLastEnvironment($lastEnvironment);
        $issue->setEventCount(1);
        $issue->setFirstSeen(new DateTimeImmutable('-2 days'));
        $issue->setLastSeen(new DateTimeImmutable('-1 day'));
        $em->persist($issue);
    }

    private function persistEventOnlyRelease(EntityManagerInterface $em, Project $project, string $title, string $release): void
    {
        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', $title));
        $issue->setTitle($title);
        $issue->setCulprit('event-only.php');
        $issue->setLevel('error');
        $issue->setEventCount(1);
        $issue->setFirstSeen(new DateTimeImmutable('-1 day'));
        $issue->setLastSeen(new DateTimeImmutable('-1 day'));
        $em->persist($issue);

        $event = new Event();
        $event->setIssue($issue);
        $event->setEventId(bin2hex(random_bytes(16)));
        $event->setReleaseVersion($release);
        $event->setEnvironment('production');
        $event->setPayload([]);
        $event->setReceivedAt(new DateTimeImmutable('-1 day'));
        $event->setEventTimestamp(new DateTimeImmutable('-1 day'));
        $em->persist($event);
    }
}
