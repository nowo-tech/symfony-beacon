<?php

declare(strict_types=1);

namespace App\Tests\Analytics;

use App\Analytics\Entity\DailyProjectStat;
use App\Identity\Entity\User;
use App\Issues\Entity\Event;
use App\Issues\Entity\Issue;
use App\Project\Entity\Project;
use App\Tests\Shared\DatabaseWebTestCase;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AnalyticsAccessTest extends DatabaseWebTestCase
{
    public function testMemberCanOpenAnalytics(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject();
        $this->login($client, $user);

        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/analytics');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="analytics-chart"]');
        self::assertSelectorExists('canvas[data-analytics-chart-target="canvas"]');
        self::assertSelectorExists('.analytics-filters');
    }

    public function testStrangerIsForbidden(): void
    {
        [$client, , $project] = $this->bootWithDemoProject('owner@example.com');
        $em = self::getContainer()->get('doctrine')->getManager();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $stranger = new User();
        $stranger->setEmail('stranger-analytics@example.com');
        $stranger->setDisplayName('Stranger');
        $stranger->setPassword($hasher->hashPassword($stranger, 'secret'));
        $em->persist($stranger);
        $em->flush();

        $this->login($client, $stranger);
        $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/analytics');
        self::assertResponseStatusCodeSame(403);
    }

    public function testServerSidePaginationLimitsRows(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('page-analytics@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tz = new DateTimeZone('UTC');
        $to = new DateTimeImmutable('today', $tz);
        $from = $to->modify('-11 days');

        for ($i = 0; $i < 12; ++$i) {
            $em->persist($this->makeStat(
                $project,
                $to->modify(\sprintf('-%d days', $i)),
            ));
        }
        $em->flush();

        $this->login($client, $user);

        $baseQuery = [
            'period' => 'custom',
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'per_page' => '10',
        ];
        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/analytics?'.http_build_query($baseQuery + ['page' => '1']),
        );
        self::assertResponseIsSuccessful();
        self::assertCount(10, $crawler->filter('table tbody tr'));
        self::assertSelectorExists('.table-pagination');
        self::assertSelectorExists('a.table-pagination__link[href*="page=2"]');

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/analytics?'.http_build_query($baseQuery + ['page' => '2']),
        );
        self::assertCount(2, $crawler->filter('table tbody tr'));
    }

    public function testPeriodPresetChangesRowCount(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('period-analytics@example.com');
        $this->login($client, $user);

        $crawler = $client->request(Request::METHOD_GET, '/projects/'.$project->getUuid().'/analytics?period=7&per_page=100');
        self::assertResponseIsSuccessful();
        self::assertCount(7, $crawler->filter('table tbody tr'));
    }

    public function testInvalidCustomRangeFallsBackWithFlash(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('invalid-range@example.com');
        $this->login($client, $user);

        $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/analytics?period=custom&from=2026-07-21&to=2026-07-01',
        );
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.flash-warning');
    }

    public function testEnvironmentFilterChangesErrorSeries(): void
    {
        [$client, $user, $project] = $this->bootWithDemoProject('filter-analytics@example.com');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tz = new DateTimeZone('UTC');
        $today = new DateTimeImmutable('today', $tz)->setTime(12, 0);

        $issue = new Issue();
        $issue->setProject($project);
        $issue->setFingerprint(hash('sha256', 'analytics-filter'));
        $issue->setTitle('Analytics filter');
        $issue->setCulprit('filter.php');
        $issue->setLevel('error');
        $em->persist($issue);

        $prod = new Event();
        $prod->setIssue($issue);
        $prod->setEventId(bin2hex(random_bytes(8)));
        $prod->setEnvironment('prod');
        $prod->setReleaseVersion('1.0.0');
        $prod->setPayload([]);
        $prod->setReceivedAt($today);
        $prod->setEventTimestamp($today);
        $em->persist($prod);

        $staging = new Event();
        $staging->setIssue($issue);
        $staging->setEventId(bin2hex(random_bytes(8)));
        $staging->setEnvironment('staging');
        $staging->setReleaseVersion('1.0.0');
        $staging->setPayload([]);
        $staging->setReceivedAt($today);
        $staging->setEventTimestamp($today);
        $em->persist($staging);
        $em->flush();

        $this->login($client, $user);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/projects/'.$project->getUuid().'/analytics?period=7&environment=prod&per_page=100',
        );
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.analytics-chart');
        $todayCell = $crawler->filter('table tbody tr')->reduce(
            static fn ($node): bool => str_contains($node->text(), $today->format('Y-m-d')),
        );
        self::assertCount(1, $todayCell);
        self::assertSame('1', trim($todayCell->filter('td')->eq(1)->text()));
        self::assertCount(0, $crawler->filter('table thead th')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Transactions'),
        ));
    }

    private function makeStat(Project $project, DateTimeImmutable $day): DailyProjectStat
    {
        $stat = new DailyProjectStat();
        $stat->setProject($project);
        $stat->setStatDate($day->setTime(0, 0));
        $stat->incrementErrorCount();

        return $stat;
    }
}
